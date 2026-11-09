<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadProjectItemRequest;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    /**
     * Display the project file explorer.
     */
    public function show(Request $request, Team $team, Project $project): View|RedirectResponse
    {
        $user = $request->user();

        if (! $project->canUserRead($user)) {
            abort(403, 'You do not have permission to view this project.');
        }

        $canOwn = $project->canUserOwn($user);
        $canEdit = $project->canUserEdit($user);
        $canRead = $project->canUserRead($user);

        $currentFolderId = $request->query('folder');
        $currentFolder = null;

        if ($currentFolderId) {
            $currentFolder = $project->items()
                ->where('id', $currentFolderId)
                ->where('kind', 'folder')
                ->first();

            if (! $currentFolder) {
                return redirect()->route('projects.show', [$team, $project]);
            }
        }

        // Build breadcrumb trail: [ ['id' => null, 'name' => $project->name], ...folders ]
        $breadcrumbs = [
            ['id' => null, 'name' => $project->name],
        ];

        if ($currentFolder) {
            foreach ($currentFolder->breadcrumbTrail() as $trailItem) {
                $breadcrumbs[] = $trailItem;
            }
        }

        // Fetch items in current directory: folders first, then files
        $items = $project->items()
            ->where('parent_id', $currentFolder?->id)
            ->with(['creator', 'updater'])
            ->orderByRaw("CASE WHEN kind = 'folder' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return view('projects.show', compact(
            'team',
            'project',
            'currentFolder',
            'breadcrumbs',
            'items',
            'canOwn',
            'canEdit',
            'canRead'
        ));
    }

    /**
     * Create a new folder inside the current directory.
     */
    public function storeFolder(Request $request, Team $team, Project $project): RedirectResponse
    {
        $user = $request->user();

        if (! $project->canUserEdit($user)) {
            abort(403, 'You do not have permission to create folders in this project.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:project_items,id'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        $this->createFolder($project, $user, $parentId, trim($validated['name']));

        $redirectUrl = route('projects.show', [$team, $project]);
        if (! empty($validated['parent_id'])) {
            $redirectUrl .= '?folder='.$validated['parent_id'];
        }

        return redirect($redirectUrl)->with('status', 'Folder created successfully!');
    }

    /**
     * Upload a file (or folder hierarchy file) via AJAX.
     */
    public function upload(UploadProjectItemRequest $request, Team $team, Project $project): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');
        $parentId = $request->input('parent_id');
        $relativePath = $request->input('relative_path');
        $projectMembership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        // If relative_path is provided (from folder upload), create nested folder tree
        $targetParentId = $parentId ? (int) $parentId : null;

        if ($targetParentId !== null) {
            $parentFolder = $project->items()
                ->whereKey($targetParentId)
                ->where('kind', 'folder')
                ->firstOrFail();
            $this->authorizeFolderWrite($project, $user, $projectMembership->role_id, $parentFolder);
        }

        if ($relativePath && str_contains($relativePath, '/')) {
            $segments = explode('/', trim($relativePath, '/'));
            // Remove the file name itself (the last segment)
            array_pop($segments);

            // If user is currently in a folder that matches the first segment, don't duplicate it
            if ($targetParentId && ! empty($segments)) {
                $currentParent = $project->items()->find($targetParentId);
                if ($currentParent && $currentParent->name === $segments[0]) {
                    array_shift($segments);
                }
            }

            foreach ($segments as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }

                $folder = $project->items()
                    ->where('project_id', $project->id)
                    ->where('parent_id', $targetParentId)
                    ->where('kind', 'folder')
                    ->where('name', $segment)
                    ->first();

                if (! $folder) {
                    $folder = $this->createFolder($project, $user, $targetParentId, $segment);
                } else {
                    $this->authorizeFolderWrite($project, $user, $projectMembership->role_id, $folder);
                }

                $targetParentId = $folder->id;
            }
        }

        // Store file securely
        $fileName = $file->getClientOriginalName();
        $storedPath = $file->store("projects/{$project->id}", 'local');
        $checksum = hash_file('sha256', $file->getRealPath());

        $item = DB::transaction(function () use ($project, $user, $projectMembership, $fileName, $file, $checksum, $storedPath, $targetParentId): ProjectItem {
            $item = $project->items()->create([
                'parent_id' => $targetParentId,
                'kind' => 'file',
                'name' => $fileName,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'checksum_sha256' => $checksum,
                'storage_backend' => 'local',
                'storage_ref' => $storedPath,
                'scan_status' => 'clean',
                'scanned_at' => now(),
                'created_by' => $user->id,
            ]);

            $fileResourceType = ResourceTypes::firstOrCreate(['code' => 'file']);
            ResourceGrants::create([
                'role_id' => $projectMembership->role_id,
                'resource_type_id' => $fileResourceType->id,
                'resource_specific' => $item->id,
                'permission_mask' => 7,
                'granted_by' => $user->id,
            ]);

            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => "Uploaded {$fileName}",
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'size' => $item->formattedSize(),
            ],
        ]);
    }

    private function createFolder(Project $project, User $user, ?int $parentId, string $name): ProjectItem
    {
        return DB::transaction(function () use ($project, $user, $parentId, $name): ProjectItem {
            $folder = $project->items()->create([
                'parent_id' => $parentId,
                'kind' => 'folder',
                'name' => $name,
                'size_bytes' => 0,
                'created_by' => $user->id,
            ]);

            $projectMembership = $project->memberships()
                ->where('user_id', $user->id)
                ->firstOrFail();
            $folderResourceType = ResourceTypes::firstOrCreate(['code' => 'folder']);

            ResourceGrants::create([
                'role_id' => $projectMembership->role_id,
                'resource_type_id' => $folderResourceType->id,
                'resource_specific' => $folder->id,
                'permission_mask' => 7,
                'granted_by' => $user->id,
            ]);

            return $folder;
        });
    }

    private function authorizeFolderWrite(Project $project, User $user, int $roleId, ProjectItem $folder): void
    {
        $folderResourceTypeId = ResourceTypes::query()
            ->where('code', 'folder')
            ->value('id');

        $permissionMask = $folderResourceTypeId === null
            ? null
            : ResourceGrants::query()
                ->where('role_id', $roleId)
                ->where('resource_type_id', $folderResourceTypeId)
                ->where('resource_specific', $folder->id)
                ->value('permission_mask');

        abort_unless(
            (int) $folder->project_id === (int) $project->id
                && $permissionMask !== null
                && ResourceGrants::hasPermission((int) $permissionMask, ResourceGrants::WRITE),
            403,
            'You do not have write permission for this folder.',
        );
    }

    /**
     * Rename an item (file or folder).
     */
    public function rename(Request $request, Team $team, Project $project, ProjectItem $item): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        abort_unless($project->canUserEdit($user), 403, 'Permission denied.');
        $this->authorizeItemWrite($project, $user, $item);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $item->update([
            'name' => trim($validated['name']),
            'updated_by' => $user->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'name' => $item->name]);
        }

        return back()->with('status', 'Item renamed successfully.');
    }

    /**
     * Soft-delete an item (file or folder).
     */
    public function destroy(Request $request, Team $team, Project $project, ProjectItem $item): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        abort_unless($project->canUserEdit($user), 403, 'Permission denied.');
        $this->authorizeItemWrite($project, $user, $item);

        // If folder, also soft-delete children
        if ($item->isFolder()) {
            $item->children()->delete();
        }

        $item->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'Item deleted.');
    }

    private function authorizeItemWrite(Project $project, User $user, ProjectItem $item): void
    {
        abort_unless((int) $item->project_id === (int) $project->id, 404);

        $projectMembership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();
        $resourceTypeId = ResourceTypes::query()
            ->where('code', $item->kind)
            ->value('id');
        $permissionMask = $resourceTypeId === null
            ? null
            : ResourceGrants::query()
                ->where('role_id', $projectMembership->role_id)
                ->where('resource_type_id', $resourceTypeId)
                ->where('resource_specific', $item->id)
                ->value('permission_mask');

        abort_unless(
            $permissionMask !== null
                && ResourceGrants::hasPermission((int) $permissionMask, ResourceGrants::WRITE),
            403,
            'You do not have write permission for this item.',
        );
    }

    /**
     * Download a file item.
     */
    public function download(Request $request, Team $team, Project $project, ProjectItem $item): StreamedResponse
    {
        $user = $request->user();

        abort_unless($project->canUserRead($user), 403, 'Permission denied.');
        $this->authorizeItemRead($project, $user, $item);

        if ($item->isFolder()) {
            abort(400, 'Cannot download a folder directly.');
        }

        $disk = $item->storage_backend ?? 'local';

        if (! $item->storage_ref || ! Storage::disk($disk)->exists($item->storage_ref)) {
            abort(404, 'File content not found on server.');
        }

        return Storage::disk($disk)->download($item->storage_ref, $item->name);
    }

    private function authorizeItemRead(Project $project, User $user, ProjectItem $item): void
    {
        abort_unless((int) $item->project_id === (int) $project->id, 404);

        $projectMembership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();
        $resourceTypeId = ResourceTypes::query()
            ->where('code', $item->kind)
            ->value('id');
        $permissionMask = $resourceTypeId === null
            ? null
            : ResourceGrants::query()
                ->where('role_id', $projectMembership->role_id)
                ->where('resource_type_id', $resourceTypeId)
                ->where('resource_specific', $item->id)
                ->value('permission_mask');

        abort_unless(
            $permissionMask !== null
                && ResourceGrants::hasPermission((int) $permissionMask, ResourceGrants::READ),
            403,
            'You do not have read permission for this item.',
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Exceptions\ClamAvScanException;
use App\Exceptions\VaultTransitException;
use App\Http\Requests\UploadProjectItemRequest;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Team;
use App\Models\User;
use App\Services\ClamAvScanner;
use App\Services\VaultFileEncryption;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ProjectController extends Controller
{
    private const PREVIEWABLE_MIME_TYPES = [
        'application/json',
        'application/pdf',
        'application/yaml',
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/csv',
        'text/markdown',
        'text/plain',
        'text/x-markdown',
    ];

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
        $projectMembership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

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

            $this->authorizeItemRead($project, $projectMembership->role_id, $currentFolder);
        }

        $canWriteCurrentFolder = $currentFolder === null
            ? $canEdit
            : ResourceGrants::hasPermission(
                $this->permissionMask($projectMembership->role_id, $currentFolder) ?? 0,
                ResourceGrants::WRITE,
            );

        // Build breadcrumb trail: [ ['id' => null, 'name' => $project->name], ...folders ]
        $breadcrumbs = [
            ['id' => null, 'name' => $project->name],
        ];

        if ($currentFolder) {
            $trail = $currentFolder->breadcrumbTrail();
            $trailIds = array_column($trail, 'id');
            $readableFolderIds = $this->readableItems(
                $project->items()->whereIn('id', $trailIds)->get(),
                $projectMembership->role_id,
            )->map(fn (ProjectItem $item): int => (int) $item->id)->all();

            foreach ($trail as $trailItem) {
                if (in_array($trailItem['id'], $readableFolderIds, true)) {
                    $breadcrumbs[] = $trailItem;
                }
            }
        }

        // Fetch items in current directory: folders first, then files
        $items = $project->items()
            ->where('parent_id', $currentFolder?->id)
            ->with(['creator', 'updater'])
            ->orderByRaw("CASE WHEN kind = 'folder' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
        $itemPermissionMasks = $this->permissionMasks($items, $projectMembership->role_id);
        $readableItemIds = $items
            ->filter(fn (ProjectItem $item): bool => ResourceGrants::hasPermission(
                $itemPermissionMasks[$item->id] ?? 0,
                ResourceGrants::READ,
            ))
            ->map(fn (ProjectItem $item): int => (int) $item->id)
            ->all();
        $writableItemIds = $items
            ->filter(fn (ProjectItem $item): bool => ResourceGrants::hasPermission(
                $itemPermissionMasks[$item->id] ?? 0,
                ResourceGrants::WRITE,
            ))
            ->map(fn (ProjectItem $item): int => (int) $item->id)
            ->all();
        $visibleItemIds = array_unique([...$readableItemIds, ...$writableItemIds]);
        $items = $items->filter(fn (ProjectItem $item): bool => in_array($item->id, $visibleItemIds, true))->values();
        $previewableItemIds = $items
            ->filter(fn (ProjectItem $item): bool => in_array((int) $item->id, $readableItemIds, true)
                && $this->isPreviewableMimeType($item->mime_type))
            ->map(fn (ProjectItem $item): int => (int) $item->id)
            ->all();

        return view('projects.show', compact(
            'team',
            'project',
            'currentFolder',
            'breadcrumbs',
            'items',
            'canOwn',
            'canEdit',
            'canRead',
            'canWriteCurrentFolder',
            'readableItemIds',
            'writableItemIds',
            'previewableItemIds',
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
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('project_items', 'id')
                    ->where('project_id', $project->id)
                    ->where('kind', 'folder'),
            ],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        if ($parentId !== null) {
            $parentFolder = $project->items()->findOrFail($parentId);
            $projectMembership = $project->memberships()
                ->where('user_id', $user->id)
                ->firstOrFail();
            $this->authorizeFolderWrite($project, $projectMembership->role_id, $parentFolder);
        }

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
    public function upload(
        UploadProjectItemRequest $request,
        Team $team,
        Project $project,
        VaultFileEncryption $encryption,
        ClamAvScanner $scanner,
    ): JsonResponse {
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
            $this->authorizeFolderWrite($project, $projectMembership->role_id, $parentFolder);
        } else {
            abort_unless($project->canUserEdit($user), 403, 'You do not have write permission for this project.');
        }

        $fileName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $checksum = hash_file('sha256', $file->getRealPath());

        if ($checksum === false) {
            throw new RuntimeException('Unable to calculate the uploaded file checksum.');
        }

        try {
            $malwareSignature = $scanner->scan($file->getRealPath());
        } catch (ClamAvScanException $exception) {
            report($exception);

            return response()->json([
                'message' => 'The virus scanning service is unavailable. The file was not uploaded. Please try again later.',
            ], 503);
        }

        $confirmationToken = $request->input('malware_confirmation');

        if ($malwareSignature !== null && $confirmationToken === null) {
            return response()->json([
                'malware_detected' => true,
                'signature' => $malwareSignature,
                'confirmation_token' => Crypt::encryptString(json_encode([
                    'user_id' => $user->id,
                    'project_id' => $project->id,
                    'checksum' => $checksum,
                    'signature' => $malwareSignature,
                    'expires_at' => now()->addMinutes(10)->timestamp,
                ], JSON_THROW_ON_ERROR)),
            ], 422);
        }

        if ($confirmationToken !== null && (
            $malwareSignature === null
            || ! $this->isValidMalwareConfirmation(
                $confirmationToken,
                $user->id,
                $project->id,
                $checksum,
                $malwareSignature,
            )
        )) {
            return response()->json([
                'message' => 'The malware confirmation is invalid or expired. Please upload the file again.',
            ], 422);
        }

        $scannedAt = now();
        $encryptedTempPath = tempnam(sys_get_temp_dir(), 'teamate-encrypted-');

        if ($encryptedTempPath === false) {
            throw new RuntimeException('Unable to create a temporary encrypted file.');
        }

        $storedPath = null;
        $itemPersisted = false;

        try {
            $encryptionMetadata = $encryption->encryptFile($file->getRealPath(), $encryptedTempPath);
            $encryptedChecksum = hash_file('sha256', $encryptedTempPath);

            if ($encryptedChecksum === false) {
                throw new RuntimeException('Unable to calculate the encrypted file checksum.');
            }

            if ($relativePath && str_contains($relativePath, '/')) {
                $segments = explode('/', trim($relativePath, '/'));
                array_pop($segments);

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
                        $this->authorizeFolderWrite($project, $projectMembership->role_id, $folder);
                    }

                    $targetParentId = $folder->id;
                }
            }

            $storedPath = "projects/{$project->id}/".Str::uuid().'.enc';
            $encryptedStream = fopen($encryptedTempPath, 'rb');

            if (! is_resource($encryptedStream)) {
                throw new RuntimeException('Unable to open the encrypted file for storage.');
            }

            try {
                $stored = Storage::disk('local')->put($storedPath, $encryptedStream);
            } finally {
                fclose($encryptedStream);
            }

            if (! $stored) {
                throw new RuntimeException('Unable to store the encrypted file.');
            }

            $item = DB::transaction(function () use (
                $project,
                $user,
                $projectMembership,
                $fileName,
                $mimeType,
                $file,
                $encryptedChecksum,
                $storedPath,
                $encryptionMetadata,
                $targetParentId,
                $malwareSignature,
                $scannedAt,
            ): ProjectItem {
                $item = $project->items()->create([
                    'parent_id' => $targetParentId,
                    'kind' => 'file',
                    'name' => $fileName,
                    'mime_type' => $mimeType,
                    'size_bytes' => $file->getSize(),
                    'checksum_sha256' => $encryptedChecksum,
                    'storage_backend' => 'local',
                    'storage_ref' => $storedPath,
                    'encryption_key_ref' => $encryptionMetadata['key_ref'],
                    'encryption_key_version' => $encryptionMetadata['key_version'],
                    'scan_status' => $malwareSignature === null ? 'clean' : 'malware',
                    'scanned_at' => $scannedAt,
                    'malware_signature' => $malwareSignature,
                    'malware_acknowledged_by' => $malwareSignature === null ? null : $user->id,
                    'malware_acknowledged_at' => $malwareSignature === null ? null : $scannedAt,
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

            $itemPersisted = true;

            return response()->json([
                'success' => true,
                'message' => "Uploaded {$fileName}",
                'item' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'size' => $item->formattedSize(),
                ],
            ]);
        } catch (VaultTransitException $exception) {
            report($exception);

            return response()->json([
                'message' => 'The file encryption service is unavailable. Please try again later.',
            ], 503);
        } finally {
            if (is_file($encryptedTempPath)) {
                unlink($encryptedTempPath);
            }

            if (! $itemPersisted && $storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }
        }
    }

    private function isValidMalwareConfirmation(
        string $token,
        int $userId,
        int $projectId,
        string $checksum,
        string $signature,
    ): bool {
        try {
            $confirmation = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return false;
        }

        return is_array($confirmation)
            && ($confirmation['user_id'] ?? null) === $userId
            && ($confirmation['project_id'] ?? null) === $projectId
            && ($confirmation['checksum'] ?? null) === $checksum
            && ($confirmation['signature'] ?? null) === $signature
            && is_int($confirmation['expires_at'] ?? null)
            && $confirmation['expires_at'] >= now()->timestamp;
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

    private function authorizeFolderWrite(Project $project, int $roleId, ProjectItem $folder): void
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
        $projectMembership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();
        $this->authorizeItemWrite($project, $projectMembership->role_id, $item);

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
        $projectMembership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();
        $this->authorizeItemWrite($project, $projectMembership->role_id, $item);

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

    private function authorizeItemWrite(Project $project, int $roleId, ProjectItem $item): void
    {
        abort_unless((int) $item->project_id === (int) $project->id, 404);

        $permissionMask = $this->permissionMask($roleId, $item);

        abort_unless(
            $permissionMask !== null
                && ResourceGrants::hasPermission((int) $permissionMask, ResourceGrants::WRITE),
            403,
            'You do not have write permission for this item.',
        );
    }

    /**
     * Download a file or a folder archive.
     */
    public function download(
        Request $request,
        Team $team,
        Project $project,
        ProjectItem $item,
        VaultFileEncryption $encryption,
    ): Response {
        $user = $request->user();

        abort_unless($project->canUserRead($user), 403, 'Permission denied.');
        $projectMembership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();
        $this->authorizeItemRead($project, $projectMembership->role_id, $item);

        if ($item->isFolder()) {
            return $this->downloadFolder($project, $item, $projectMembership->role_id, $encryption);
        }

        $temporaryPath = $this->decryptToTemporaryFile($item, $encryption);

        return response()->download($temporaryPath, $item->name)->deleteFileAfterSend(true);
    }

    /**
     * Display a safe file type inline.
     */
    public function preview(
        Request $request,
        Team $team,
        Project $project,
        ProjectItem $item,
        VaultFileEncryption $encryption,
    ): Response {
        $user = $request->user();

        abort_unless($project->canUserRead($user), 403, 'Permission denied.');
        $projectMembership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();
        $this->authorizeItemRead($project, $projectMembership->role_id, $item);

        abort_unless($item->isFile(), 404);

        $mimeType = $item->mime_type;

        abort_unless(
            $this->isPreviewableMimeType($mimeType),
            415,
            'This file type cannot be previewed. Download it instead.',
        );

        $temporaryPath = $this->decryptToTemporaryFile($item, $encryption);

        return response()->file($temporaryPath, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    private function decryptToTemporaryFile(ProjectItem $item, VaultFileEncryption $encryption): string
    {
        if (! $item->storage_ref || ! $item->encryption_key_ref) {
            abort(500, 'Encrypted file metadata is missing.');
        }

        $disk = Storage::disk($item->storage_backend ?? 'local');

        if (! $disk->exists($item->storage_ref)) {
            abort(404, 'File content not found on server.');
        }

        $source = $disk->readStream($item->storage_ref);

        if (! is_resource($source)) {
            throw new RuntimeException('Unable to read the encrypted file.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'teamate-decrypted-');

        if ($temporaryPath === false) {
            fclose($source);

            throw new RuntimeException('Unable to create a temporary decrypted file.');
        }

        if (! chmod($temporaryPath, 0600)) {
            fclose($source);
            unlink($temporaryPath);

            throw new RuntimeException('Unable to secure the temporary decrypted file.');
        }

        $destination = fopen($temporaryPath, 'wb');

        if (! is_resource($destination)) {
            fclose($source);
            unlink($temporaryPath);

            throw new RuntimeException('Unable to write the temporary decrypted file.');
        }

        $decryptionSucceeded = false;

        try {
            $encryption->decryptStream($source, $destination, $item->encryption_key_ref);
            $decryptionSucceeded = true;
        } catch (VaultTransitException $exception) {
            report($exception);

            abort(503, 'The file encryption service is unavailable. Please try again later.');
        } finally {
            fclose($source);
            fclose($destination);

            if (! $decryptionSucceeded && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        return $temporaryPath;
    }

    private function downloadFolder(
        Project $project,
        ProjectItem $folder,
        int $roleId,
        VaultFileEncryption $encryption,
    ): Response {
        $items = $project->items()->get([
            'id',
            'parent_id',
            'kind',
            'name',
            'storage_backend',
            'storage_ref',
            'encryption_key_ref',
            'encryption_key_version',
        ]);
        $childrenByParent = $items->groupBy('parent_id');
        $permissionMasks = $this->permissionMasks($items, $roleId);
        $rootName = $this->archivePathSegment($folder);
        $directories = [$rootName];
        $files = [];
        $usedPaths = [];

        /** @var array<int, array{0: int, 1: string}> $pendingFolders */
        $pendingFolders = [[$folder->id, $rootName]];

        while ($pendingFolders !== []) {
            [$parentId, $parentPath] = array_pop($pendingFolders);

            foreach ($childrenByParent->get($parentId, collect()) as $child) {
                $permissionMask = $permissionMasks[$child->id] ?? 0;

                if (! ResourceGrants::hasPermission($permissionMask, ResourceGrants::READ)) {
                    continue;
                }

                $archivePath = $parentPath.'/'.$this->archivePathSegment($child);

                if (isset($usedPaths[$archivePath])) {
                    $archivePath .= '-'.$child->id;
                }

                $usedPaths[$archivePath] = true;

                if ($child->isFolder()) {
                    $directories[] = $archivePath;
                    $pendingFolders[] = [(int) $child->id, $archivePath];
                } elseif ($child->isFile()) {
                    $files[] = ['item' => $child, 'path' => $archivePath];
                }
            }
        }

        $archivePath = tempnam(sys_get_temp_dir(), 'teamate-folder-');

        if ($archivePath === false) {
            throw new RuntimeException('Unable to create a temporary folder archive.');
        }

        $archive = new ZipArchive;
        $openResult = $archive->open($archivePath, ZipArchive::OVERWRITE);

        if ($openResult !== true) {
            unlink($archivePath);

            throw new RuntimeException('Unable to open the temporary folder archive.');
        }

        $archiveIsOpen = true;
        $archiveIsReady = false;
        $temporaryFilePaths = [];

        try {
            foreach (array_unique($directories) as $directory) {
                if (! $archive->addEmptyDir($directory)) {
                    throw new RuntimeException('Unable to add a folder to the archive.');
                }
            }

            foreach ($files as $file) {
                $item = $file['item'];
                $temporaryFilePath = $this->decryptToTemporaryFile($item, $encryption);
                $temporaryFilePaths[] = $temporaryFilePath;

                if (! $archive->addFile($temporaryFilePath, $file['path'])) {
                    throw new RuntimeException('Unable to add a file to the folder archive.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Unable to finish the folder archive.');
            }

            $archiveIsOpen = false;
            $download = response()->download($archivePath, $rootName.'.zip')
                ->deleteFileAfterSend(true);
            $archiveIsReady = true;

            return $download;
        } finally {
            if ($archiveIsOpen) {
                $archive->close();
            }

            foreach ($temporaryFilePaths as $temporaryFilePath) {
                if (is_file($temporaryFilePath)) {
                    unlink($temporaryFilePath);
                }
            }

            if (! $archiveIsReady && is_file($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    private function archivePathSegment(ProjectItem $item): string
    {
        $name = str_replace(['/', '\\', ':', "\0"], '-', $item->name);
        $name = trim(preg_replace('/[\x00-\x1F]/', '-', $name) ?? '', ' .');

        return in_array($name, ['', '.', '..'], true) ? 'item-'.$item->id : $name;
    }

    private function isPreviewableMimeType(string|false|null $mimeType): bool
    {
        return is_string($mimeType) && in_array($mimeType, self::PREVIEWABLE_MIME_TYPES, true);
    }

    private function authorizeItemRead(Project $project, int $roleId, ProjectItem $item): void
    {
        abort_unless((int) $item->project_id === (int) $project->id, 404);

        $permissionMask = $this->permissionMask($roleId, $item);

        abort_unless(
            $permissionMask !== null
                && ResourceGrants::hasPermission($permissionMask, ResourceGrants::READ),
            403,
            'You do not have read permission for this item.',
        );
    }

    /**
     * @param  Collection<int, ProjectItem>  $items
     * @return Collection<int, ProjectItem>
     */
    private function readableItems(Collection $items, int $roleId): Collection
    {
        $permissionMasks = $this->permissionMasks($items, $roleId);

        return $items->filter(function (ProjectItem $item) use ($permissionMasks): bool {
            $permissionMask = $permissionMasks[$item->id] ?? null;

            return $permissionMask !== null
                && ResourceGrants::hasPermission((int) $permissionMask, ResourceGrants::READ);
        })->values();
    }

    /**
     * @param  Collection<int, ProjectItem>  $items
     * @return array<int, int>
     */
    private function permissionMasks(Collection $items, int $roleId): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $resourceTypeIds = ResourceTypes::query()
            ->whereIn('code', ['file', 'folder'])
            ->pluck('id', 'code');
        $permissionMasks = ResourceGrants::query()
            ->where('role_id', $roleId)
            ->whereIn('resource_type_id', $resourceTypeIds->values())
            ->whereIn('resource_specific', $items->map(fn (ProjectItem $item): int => (int) $item->id)->all())
            ->get(['resource_type_id', 'resource_specific', 'permission_mask'])
            ->keyBy(fn (ResourceGrants $grant): string => $grant->resource_type_id.':'.$grant->resource_specific);

        return $items->mapWithKeys(function (ProjectItem $item) use ($permissionMasks, $resourceTypeIds): array {
            $resourceTypeId = $resourceTypeIds->get($item->kind);
            $permissionMask = $resourceTypeId === null
                ? null
                : $permissionMasks->get($resourceTypeId.':'.$item->id)?->permission_mask;

            return $permissionMask === null ? [] : [(int) $item->id => (int) $permissionMask];
        })->all();
    }

    private function permissionMask(int $roleId, ProjectItem $item): ?int
    {
        $resourceTypeId = ResourceTypes::query()
            ->where('code', $item->kind)
            ->value('id');
        $permissionMask = $resourceTypeId === null
            ? null
            : ResourceGrants::query()
                ->where('role_id', $roleId)
                ->where('resource_type_id', $resourceTypeId)
                ->where('resource_specific', $item->id)
                ->value('permission_mask');

        return $permissionMask === null ? null : (int) $permissionMask;
    }
}

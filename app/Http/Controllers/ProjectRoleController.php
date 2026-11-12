<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\ProjectMember;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Roles;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectRoleController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PERMISSION_OPTIONS = [
        0 => 'None',
        1 => 'Own',
        2 => 'Write',
        3 => 'Own + Write',
        4 => 'Read',
        5 => 'Own + Read',
        6 => 'Write + Read',
        7 => 'Own + Write + Read',
    ];

    public function index(Request $request, Team $team, Project $project): View
    {
        $user = $request->user();
        $this->authorizeProjectRoleManagement($team, $project, $user);

        $resources = $this->ownedResources($project, $user);
        $resourceTree = $this->resourceTree($resources['folder'], $resources['file']);
        $rolePrefix = $this->roleCodePrefix($project);

        $roles = Roles::query()
            ->where('scope_type', 'project')
            ->where('team_id', $team->id)
            ->withCount(['memberships' => fn (Builder $query) => $query
                ->where('scope_id', $project->id)
                ->where('scope_type', 'project')])
            ->with('resourceGrants.resourceType')
            ->orderBy('name')
            ->get()
            ->filter(fn (Roles $role): bool => str_starts_with($role->code, $rolePrefix))
            ->values();

        return view('projects.roles', [
            'team' => $team,
            'project' => $project,
            'resources' => $resources,
            'resourceTree' => $resourceTree,
            'roles' => $roles,
            'permissionOptions' => self::PERMISSION_OPTIONS,
        ]);
    }

    public function store(Request $request, Team $team, Project $project): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeProjectRoleManagement($team, $project, $user);

        $validated = $request->validate($this->roleRules());
        $resources = $this->ownedResources($project, $user);

        $role = DB::transaction(function () use ($team, $project, $user, $validated, $resources): Roles {
            $role = Roles::create([
                'code' => $this->uniqueRoleCode($project, $validated['name']),
                'name' => trim($validated['name']),
                'scope_type' => 'project',
                'team_id' => $team->id,
                'is_system' => false,
            ]);

            $this->syncRolePermissions(
                $role,
                $project,
                $user,
                $resources,
                $validated['permissions'] ?? [],
                $validated['apply_to_descendants'] ?? [],
            );

            return $role;
        });

        return redirect()
            ->route('projects.roles.index', [$team, $project])
            ->with('status', __('Role created successfully.'));
    }

    public function update(Request $request, Team $team, Project $project, Roles $role): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeProjectRoleManagement($team, $project, $user);
        $this->ensureEditableRole($project, $team, $role);

        $validated = $request->validate($this->roleRules());
        $resources = $this->ownedResources($project, $user);

        DB::transaction(function () use ($project, $user, $role, $validated, $resources): void {
            $role->update(['name' => trim($validated['name'])]);
            $this->syncRolePermissions(
                $role,
                $project,
                $user,
                $resources,
                $validated['permissions'] ?? [],
                $validated['apply_to_descendants'] ?? [],
            );

            ProjectMember::query()
                ->where('project_id', $project->id)
                ->whereIn('user_id', Membership::query()
                    ->where('scope_id', $project->id)
                    ->where('scope_type', 'project')
                    ->where('role_id', $role->id)
                    ->select('user_id'))
                ->update(['role' => $role->name]);
        });

        return redirect()
            ->route('projects.roles.index', [$team, $project])
            ->with('status', __('Role updated successfully.'));
    }

    /** @return array<string, array<int, string>> */
    private function roleRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array:project,file,folder'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['required', 'integer', 'between:0,7'],
            'apply_to_descendants' => ['sometimes', 'array'],
            'apply_to_descendants.*' => ['accepted'],
        ];
    }

    /**
     * @return array{project: Collection<int, Project>, file: Collection<int, ProjectItem>, folder: Collection<int, ProjectItem>}
     */
    private function ownedResources(Project $project, User $user): array
    {
        $membership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $resourceTypeIds = ResourceTypes::query()
            ->whereIn('code', ['file', 'folder'])
            ->pluck('id', 'code');
        $ownedGrants = ResourceGrants::query()
            ->where('role_id', $membership->role_id)
            ->whereIn('resource_type_id', $resourceTypeIds->values())
            ->whereRaw('(permission_mask & ?) = ?', [ResourceGrants::OWN, ResourceGrants::OWN])
            ->get(['resource_type_id', 'resource_specific']);

        $resources = [
            'project' => collect([$project]),
            'file' => collect(),
            'folder' => collect(),
        ];

        $items = $project->items()->orderBy('name')->get();
        $itemsById = $items->keyBy('id');
        $ownedIdsByKind = [];

        foreach (['file', 'folder'] as $resourceTypeCode) {
            $resourceTypeId = $resourceTypeIds->get($resourceTypeCode);

            if ($resourceTypeId === null) {
                continue;
            }

            $ownedIdsByKind[$resourceTypeCode] = $ownedGrants
                ->where('resource_type_id', $resourceTypeId)
                ->pluck('resource_specific');
        }

        $manageable = [];
        $isManageable = function (ProjectItem $item) use (&$isManageable, &$manageable, $ownedIdsByKind, $itemsById): bool {
            if (array_key_exists($item->id, $manageable)) {
                return $manageable[$item->id];
            }

            $isOwned = ($ownedIdsByKind[$item->kind] ?? collect())
                ->contains((int) $item->id);
            $parent = $item->parent_id === null ? null : $itemsById->get($item->parent_id);
            $isWithinOwnedFolder = $parent instanceof ProjectItem
                && $parent->isFolder()
                && $isManageable($parent);

            $manageable[$item->id] = $isOwned || $isWithinOwnedFolder;

            return $manageable[$item->id];
        };

        foreach (['file', 'folder'] as $resourceTypeCode) {
            $resources[$resourceTypeCode] = $items
                ->where('kind', $resourceTypeCode)
                ->filter(fn (ProjectItem $item): bool => $isManageable($item))
                ->values();
        }

        return $resources;
    }

    /**
     * @param  Collection<int, ProjectItem>  $folders
     * @param  Collection<int, ProjectItem>  $files
     * @return list<array{item: ProjectItem, depth: int, ancestorIds: list<int>, hasChildren: bool}>
     */
    private function resourceTree(Collection $folders, Collection $files): array
    {
        $items = $folders->concat($files)->keyBy('id');
        $childrenByParent = $items->groupBy('parent_id');

        $roots = $items
            ->filter(fn (ProjectItem $item): bool => $item->parent_id === null || ! $items->has($item->parent_id))
            ->sortBy(fn (ProjectItem $item): array => [$item->isFolder() ? 0 : 1, mb_strtolower($item->name)])
            ->map(fn (ProjectItem $item): array => [
                'item' => $item,
                'depth' => 0,
                'ancestorIds' => [],
            ])
            ->values();
        $pending = $roots->reverse()->values()->all();
        $tree = [];

        while ($pending !== []) {
            $node = array_pop($pending);
            $item = $node['item'];
            $children = $childrenByParent->get($item->id, collect())
                ->sortBy(fn (ProjectItem $child): array => [$child->isFolder() ? 0 : 1, mb_strtolower($child->name)])
                ->values();

            $tree[] = [
                'item' => $item,
                'depth' => $node['depth'],
                'ancestorIds' => $node['ancestorIds'],
                'hasChildren' => $children->isNotEmpty(),
            ];

            $ancestorIds = [...$node['ancestorIds'], (int) $item->id];

            foreach ($children->reverse()->values() as $child) {
                $pending[] = [
                    'item' => $child,
                    'depth' => $node['depth'] + 1,
                    'ancestorIds' => $ancestorIds,
                ];
            }
        }

        return $tree;
    }

    /**
     * @param  array{project: Collection<int, Project>, file: Collection<int, ProjectItem>, folder: Collection<int, ProjectItem>}  $resources
     * @param  array<string, array<int|string, int|string>>  $permissions
     * @param  array<int|string, string>  $applyToDescendants
     */
    private function syncRolePermissions(
        Roles $role,
        Project $project,
        User $user,
        array $resources,
        array $permissions,
        array $applyToDescendants,
    ): void {
        foreach ($permissions as $resourceTypeCode => $resourcePermissions) {
            $resourceType = ResourceTypes::query()
                ->where('code', $resourceTypeCode)
                ->firstOrFail();

            foreach ($resourcePermissions as $resourceId => $permissionMask) {
                $submittedResourceId = $resourceId;
                $resourceId = filter_var((string) $submittedResourceId, FILTER_VALIDATE_INT);

                if ($resourceId === false) {
                    throw ValidationException::withMessages([
                        "permissions.{$resourceTypeCode}.{$submittedResourceId}" => __('The selected resource is invalid.'),
                    ]);
                }

                abort_unless(
                    $resources[$resourceTypeCode]->contains(fn (Project|ProjectItem $resource): bool => (int) $resource->id === $resourceId),
                    403,
                    __('You can only configure resources that you own.'),
                );

                $permissionMask = (int) $permissionMask;
                $this->syncResourceGrant($role, $user, (int) $resourceType->id, $resourceId, $permissionMask);
            }
        }

        if ($applyToDescendants === []) {
            return;
        }

        $folderPermissions = $permissions['folder'] ?? [];
        $folderIds = [];

        foreach (array_keys($applyToDescendants) as $submittedFolderId) {
            $folderId = filter_var((string) $submittedFolderId, FILTER_VALIDATE_INT);

            abort_unless(
                $folderId !== false
                    && $resources['folder']->contains(fn (ProjectItem $folder): bool => (int) $folder->id === $folderId),
                403,
                __('You can only apply permissions to folders that you own.'),
            );

            if (! array_key_exists($folderId, $folderPermissions)) {
                throw ValidationException::withMessages([
                    "permissions.folder.{$folderId}" => __('A permission must be selected for this folder.'),
                ]);
            }

            $folderIds[] = (int) $folderId;
        }

        $allItems = $project->items()->get(['id', 'parent_id', 'kind']);
        $itemsByParent = $allItems->groupBy('parent_id');
        $itemsById = $allItems->keyBy('id');
        $resourceTypeIds = ResourceTypes::query()
            ->whereIn('code', ['file', 'folder'])
            ->pluck('id', 'code');
        $depth = function (int $itemId) use (&$depth, $itemsById): int {
            $item = $itemsById->get($itemId);

            return $item instanceof ProjectItem && $item->parent_id !== null
                ? 1 + $depth((int) $item->parent_id)
                : 0;
        };
        usort($folderIds, fn (int $left, int $right): int => $depth($left) <=> $depth($right));

        foreach ($folderIds as $folderId) {
            $permissionMask = (int) $folderPermissions[$folderId];
            $folderResourceTypeId = $resourceTypeIds->get('folder');

            if ($folderResourceTypeId !== null) {
                $this->syncResourceGrant($role, $user, (int) $folderResourceTypeId, $folderId, $permissionMask);
            }

            $pendingFolderIds = [$folderId];

            while ($pendingFolderIds !== []) {
                $parentId = array_pop($pendingFolderIds);

                foreach ($itemsByParent->get($parentId, collect()) as $item) {
                    if ($item->isFolder()) {
                        $pendingFolderIds[] = (int) $item->id;
                    }

                    $resourceTypeId = $resourceTypeIds->get($item->kind);

                    if ($resourceTypeId === null) {
                        continue;
                    }

                    $this->syncResourceGrant($role, $user, (int) $resourceTypeId, (int) $item->id, $permissionMask);
                }
            }
        }
    }

    private function syncResourceGrant(
        Roles $role,
        User $user,
        int $resourceTypeId,
        int $resourceId,
        int $permissionMask,
    ): void {
        $grant = $role->resourceGrants()
            ->where('resource_type_id', $resourceTypeId)
            ->where('resource_specific', $resourceId);

        if ($permissionMask === 0) {
            $grant->delete();

            return;
        }

        ResourceGrants::updateOrCreate(
            [
                'role_id' => $role->id,
                'resource_type_id' => $resourceTypeId,
                'resource_specific' => $resourceId,
            ],
            [
                'permission_mask' => $permissionMask,
                'granted_by' => $user->id,
            ],
        );
    }

    private function uniqueRoleCode(Project $project, string $name): string
    {
        $baseCode = $this->roleCodePrefix($project).Str::slug(Str::limit($name, 65, ''));
        $baseCode = rtrim($baseCode, '-');
        $code = $baseCode;
        $suffix = 1;

        while (Roles::query()->where('code', $code)->where('scope_type', 'project')->exists()) {
            $code = Str::limit($baseCode, 95, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    private function ensureEditableRole(Project $project, Team $team, Roles $role): void
    {
        abort_if(
            $role->scope_type !== 'project'
                || (int) $role->team_id !== (int) $team->id
                || $role->is_system
                || ! str_starts_with($role->code, $this->roleCodePrefix($project)),
            404,
        );
    }

    private function authorizeProjectRoleManagement(Team $team, Project $project, User $user): void
    {
        abort_unless((int) $project->team_id === (int) $team->id, 404);
        abort_unless($project->canUserOwn($user), 403);
    }

    private function roleCodePrefix(Project $project): string
    {
        return 'project_'.$project->id.'_';
    }
}

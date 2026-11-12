<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\ResourceGrants;
use App\Models\ResourceTypes;
use App\Models\Roles;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TeamRoleController extends Controller
{
    public function index(Request $request, Team $team): View
    {
        $user = $request->user();

        abort_unless($team->canUserWriteTeamRoles($user), 403);

        $resourceTypes = ResourceTypes::query()->orderBy('code')->get();
        $manageableResourceTypes = $resourceTypes
            ->filter(fn (ResourceTypes $resourceType): bool => $team->canUserHavePermission(
                $user,
                $resourceType->code,
                ResourceGrants::OWN,
            ))
            ->values();

        $roles = $this->availableRoles($team)
            ->withCount(['memberships' => fn (Builder $query) => $query
                ->where('scope_id', $team->id)
                ->where('scope_type', 'team')])
            ->with([
                'resourceGrants' => fn (Relation $relation) => $relation
                    ->where('resource_specific', $team->id)
                    ->with('resourceType'),
            ])
            ->orderBy('name')
            ->get();

        $permissionOptions = [
            0 => 'None',
            1 => 'Own',
            2 => 'Write',
            3 => 'Own + Write',
            4 => 'Read',
            5 => 'Own + Read',
            6 => 'Write + Read',
            7 => 'Own + Write + Read',
        ];

        return view('teams.roles', compact('team', 'roles', 'manageableResourceTypes', 'permissionOptions'));
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        abort_unless($team->canUserWriteTeamRoles($user), 403);

        $validated = $request->validate($this->roleRules());

        DB::transaction(function () use ($team, $user, $validated): void {
            $role = Roles::create([
                'code' => $this->uniqueRoleCode($team, $validated['name']),
                'name' => trim($validated['name']),
                'scope_type' => 'team',
                'team_id' => $team->id,
                'is_system' => false,
            ]);

            $this->syncRolePermissions($team, $user, $role, $validated['permissions'] ?? []);
        });

        return redirect()
            ->route('teams.roles.index', $team)
            ->with('status', 'Role created successfully.');
    }

    public function update(Request $request, Team $team, Roles $role): RedirectResponse
    {
        $user = $request->user();

        abort_unless($team->canUserWriteTeamRoles($user), 403);
        $this->ensureEditableRole($team, $role);

        $validated = $request->validate($this->roleRules());

        DB::transaction(function () use ($team, $user, $role, $validated): void {
            $role->update(['name' => trim($validated['name'])]);
            $this->syncRolePermissions($team, $user, $role, $validated['permissions'] ?? []);

            TeamMember::query()
                ->where('team_id', $team->id)
                ->whereIn('user_id', Membership::query()
                    ->where('scope_id', $team->id)
                    ->where('scope_type', 'team')
                    ->where('role_id', $role->id)
                    ->select('user_id'))
                ->update(['role' => $role->name]);
        });

        return redirect()
            ->route('teams.roles.index', $team)
            ->with('status', 'Role updated successfully.');
    }

    public function assignMember(Request $request, Team $team, TeamMember $member): RedirectResponse
    {
        $user = $request->user();

        abort_unless($team->canUserWriteTeamMembers($user), 403);
        abort_unless((int) $member->team_id === (int) $team->id, 404);
        abort_if((int) $member->user_id === (int) $user->id, 403, 'You cannot change your own role.');

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $role = $this->availableRoles($team)
            ->whereKey($validated['role_id'])
            ->firstOrFail();

        abort_unless($team->canUserAssignRole($user, $role), 403);

        DB::transaction(function () use ($team, $member, $role): void {
            Membership::updateOrCreate(
                [
                    'user_id' => $member->user_id,
                    'scope_id' => $team->id,
                    'scope_type' => 'team',
                ],
                [
                    'role_id' => $role->id,
                ],
            );

            $member->update(['role' => $role->name]);
        });

        return redirect()
            ->route('teams.show', $team)
            ->with('status', 'Member role updated successfully.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function roleRules(): array
    {
        $resourceCodes = ResourceTypes::query()->orderBy('code')->pluck('code')->all();
        $permissionArrayRule = 'array';

        if ($resourceCodes !== []) {
            $permissionArrayRule .= ':'.implode(',', $resourceCodes);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['sometimes', $permissionArrayRule],
            'permissions.*' => ['required', 'integer', 'between:0,7'],
        ];
    }

    /**
     * @param  array<string, int|string>  $permissions
     */
    private function syncRolePermissions(Team $team, User $user, Roles $role, array $permissions): void
    {
        foreach ($permissions as $resourceTypeCode => $permissionMask) {
            $resourceType = ResourceTypes::query()
                ->where('code', $resourceTypeCode)
                ->firstOrFail();

            $resourceGrantQuery = $role->resourceGrants()
                ->where('resource_type_id', $resourceType->id)
                ->where('resource_specific', $team->id);

            $permissionMask = (int) $permissionMask;
            $currentMask = (int) ($resourceGrantQuery->value('permission_mask') ?? 0);

            if ($permissionMask === $currentMask) {
                continue;
            }

            abort_unless($team->canUserHavePermission($user, $resourceTypeCode, ResourceGrants::OWN), 403);

            if ($permissionMask === 0) {
                $resourceGrantQuery->delete();

                continue;
            }

            ResourceGrants::updateOrCreate(
                [
                    'role_id' => $role->id,
                    'resource_type_id' => $resourceType->id,
                    'resource_specific' => $team->id,
                ],
                [
                    'permission_mask' => $permissionMask,
                    'granted_by' => $user->id,
                ],
            );
        }
    }

    private function uniqueRoleCode(Team $team, string $name): string
    {
        $baseCode = 'team_'.$team->id.'_'.Str::slug(Str::limit($name, 70, ''));
        $baseCode = trim($baseCode, '-');
        $code = $baseCode;
        $suffix = 1;

        while (Roles::query()->where('code', $code)->where('scope_type', 'team')->exists()) {
            $code = Str::limit($baseCode, 95, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    private function ensureEditableRole(Team $team, Roles $role): void
    {
        abort_if(
            $role->scope_type !== 'team' || $role->team_id !== $team->id || $role->is_system,
            404,
        );
    }

    /**
     * @return Builder<Roles>
     */
    private function availableRoles(Team $team): Builder
    {
        return Roles::query()
            ->where('scope_type', 'team')
            ->where(function (Builder $query) use ($team): void {
                $query->where('team_id', $team->id)
                    ->orWhere(function (Builder $query): void {
                        $query->whereNull('team_id')
                            ->where('code', 'guest')
                            ->where('is_system', true);
                    });
            });
    }
}

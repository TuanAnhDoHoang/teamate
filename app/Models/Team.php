<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Team extends Model
{
    protected $table = 'teams';

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this, Pivot, 'pivot'> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<TeamMember, $this> */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'scope_id')
            ->whereHas('role', fn ($query) => $query->where('scope_type', 'team'));
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Determine the role of a user in this team.
     */
    public function roleFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $membership = $this->memberships()
            ->where('user_id', $user->id)
            ->with('role')
            ->first();

        return $membership?->role->name
            ?? $this->teamMembers()->where('user_id', $user->id)->value('role');
    }

    /**
     * Can user manage team / projects (Own permission).
     */
    public function canUserOwn(?User $user): bool
    {
        return $this->userHasTeamResourcePermission($user, 'team', ResourceGrants::OWN);
    }

    /**
     * Can user create/edit content & files (Edit permission).
     */
    public function canUserEdit(?User $user): bool
    {
        return $this->userHasTeamResourcePermission($user, 'team', ResourceGrants::WRITE);
    }

    /**
     * Can user read content (Read permission).
     */
    public function canUserRead(?User $user): bool
    {
        return $this->userHasTeamResourcePermission($user, 'team', ResourceGrants::READ);
    }

    public function canUserWriteTeamMembers(?User $user): bool
    {
        return $this->userHasTeamResourcePermission($user, 'team_member', ResourceGrants::WRITE);
    }

    public function canUserWriteTeamRoles(?User $user): bool
    {
        return $this->userHasTeamResourcePermission($user, 'team_role', ResourceGrants::WRITE);
    }

    public function canUserHavePermission(?User $user, string $resourceTypeCode, int $permission): bool
    {
        return $this->userHasTeamResourcePermission($user, $resourceTypeCode, $permission);
    }

    public function canUserAssignRole(?User $user, Roles $role): bool
    {
        if (! $user || ! $this->canUserWriteTeamMembers($user)) {
            return false;
        }

        $isAssignableRole = $role->scope_type === 'team'
            && ($role->team_id === $this->id
                || ($role->team_id === null && $role->code === 'guest' && $role->is_system));

        if (! $isAssignableRole) {
            return false;
        }

        return true;
    }

    public function permissionMaskForUser(?User $user, string $resourceTypeCode): ?int
    {
        if (! $user || ! $this->teamMembers()->where('user_id', $user->id)->exists()) {
            return null;
        }

        $resourceTypeId = ResourceTypes::where('code', $resourceTypeCode)->value('id');

        if ($resourceTypeId === null) {
            return null;
        }

        $permissionMask = Membership::query()
            ->where('user_id', $user->id)
            ->where('scope_id', $this->id)
            ->whereHas('role', function ($query) {
                $query->where('scope_type', 'team')
                    ->where(function ($query) {
                        $query->where('team_id', $this->id)
                            ->orWhere(function ($query) {
                                $query->whereNull('team_id')
                                    ->where('code', 'guest')
                                    ->where('is_system', true);
                            });
                    });
            })
            ->join('roles', 'roles.id', '=', 'memberships.role_id')
            ->join('resource_grants', 'resource_grants.role_id', '=', 'roles.id')
            ->where('resource_grants.resource_type_id', $resourceTypeId)
            ->where('resource_grants.resource_specific', $this->id)
            ->value('resource_grants.permission_mask');

        return $permissionMask === null ? null : (int) $permissionMask;
    }

    private function userHasTeamResourcePermission(?User $user, string $resourceTypeCode, int $permission): bool
    {
        $permissionMask = $this->permissionMaskForUser($user, $resourceTypeCode);

        return $permissionMask !== null && ResourceGrants::hasPermission($permissionMask, $permission);
    }
}

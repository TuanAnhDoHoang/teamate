<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'projects';

    protected $fillable = [
        'team_id',
        'owner_id',
        'name',
        'slug',
        'description',
        'status',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'scope_id')
            ->where('scope_type', 'project');
    }

    /** @return HasMany<ProjectMember, $this> */
    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<ProjectItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class);
    }

    /** @return HasMany<ProjectItem, $this> */
    public function rootItems(): HasMany
    {
        return $this->hasMany(ProjectItem::class)->whereNull('parent_id');
    }

    public function canUserOwn(?User $user): bool
    {
        return $this->userHasProjectPermission($user, ResourceGrants::OWN);
    }

    public function canUserEdit(?User $user): bool
    {
        return $this->userHasProjectPermission($user, ResourceGrants::WRITE);
    }

    public function canUserRead(?User $user): bool
    {
        return $this->userHasProjectPermission($user, ResourceGrants::READ);
    }

    private function userHasProjectPermission(?User $user, int $permission): bool
    {
        if (! $user || ! $this->projectMembers()->where('user_id', $user->id)->exists()) {
            return false;
        }

        $resourceTypeId = ResourceTypes::query()
            ->where('code', 'project')
            ->value('id');

        if ($resourceTypeId === null) {
            return false;
        }

        $permissionMask = Membership::query()
            ->where('memberships.user_id', $user->id)
            ->where('memberships.scope_id', $this->id)
            ->where('memberships.scope_type', 'project')
            ->join('roles', 'roles.id', '=', 'memberships.role_id')
            ->join('resource_grants', 'resource_grants.role_id', '=', 'roles.id')
            ->where('roles.scope_type', 'project')
            ->where(function ($query) {
                $query->where('roles.team_id', $this->team_id)
                    ->orWhere(function ($query) {
                        $query->whereNull('roles.team_id')
                            ->where('roles.code', 'guest')
                            ->where('roles.is_system', true);
                    });
            })
            ->where('resource_grants.resource_type_id', $resourceTypeId)
            ->where('resource_grants.resource_specific', $this->id)
            ->value('resource_grants.permission_mask');

        return $permissionMask !== null
            && ResourceGrants::hasPermission((int) $permissionMask, $permission);
    }
}

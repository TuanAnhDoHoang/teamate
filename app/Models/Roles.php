<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roles extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'code',
        'name',
        'scope_type',
        'team_id',
        'is_system',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'is_system' => 'boolean',
    ];

    /** @return HasMany<ResourceGrants, $this> */
    public function resourceGrants(): HasMany
    {
        return $this->hasMany(ResourceGrants::class, 'role_id');
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'role_id');
    }
}

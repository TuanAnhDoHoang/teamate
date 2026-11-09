<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    protected $table = 'memberships';

    protected $fillable = [
        'user_id',
        'role_id',
        'scope_id',
    ];

    // User được gán role
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Role được gán
    /** @return BelongsTo<Roles, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Roles::class);
    }
}

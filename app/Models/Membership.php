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
        'scope_type',
    ];

    protected static function booted(): void
    {
        static::creating(function (Membership $membership): void {
            if (! $membership->scope_type) {
                $membership->scope_type = Roles::query()
                    ->whereKey($membership->role_id)
                    ->value('scope_type');
            }
        });
    }

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

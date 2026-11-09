<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceGrants extends Model
{
    protected $table = 'resource_grants';

    public const OWN = 1;

    public const WRITE = 2;

    public const READ = 4;

    // Chỉ có created_at, không có updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'role_id',
        'resource_type_id',
        'resource_specific',
        'permission_mask',
        'granted_by',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'resource_type_id' => 'integer',
        'resource_specific' => 'integer',
        'permission_mask' => 'integer',
        'granted_by' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Roles, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Roles::class);
    }

    /** @return BelongsTo<ResourceTypes, $this> */
    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceTypes::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Decode a permission mask into its permission names and symbols.
     *
     * @return array<string, string>
     */
    public static function decodePermissionMask(int $permissionMask): array
    {
        if ($permissionMask < 1 || $permissionMask > 7) {
            throw new \InvalidArgumentException('Permission mask must be between 1 and 7.');
        }

        $permissions = [];

        if (($permissionMask & self::OWN) === self::OWN) {
            $permissions['own'] = 'o';
        }

        if (($permissionMask & self::WRITE) === self::WRITE) {
            $permissions['write'] = 'w';
        }

        if (($permissionMask & self::READ) === self::READ) {
            $permissions['read'] = 'r';
        }

        return $permissions;
    }

    public static function hasPermission(int $permissionMask, int $permission): bool
    {
        if ($permissionMask < 1 || $permissionMask > 7 || ! in_array($permission, [self::OWN, self::WRITE, self::READ], true)) {
            return false;
        }

        return ($permissionMask & $permission) === $permission;
    }
}

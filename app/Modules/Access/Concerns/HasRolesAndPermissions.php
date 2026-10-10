<?php

namespace App\Modules\Access\Concerns;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\RolePermission;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/** Added to the User model. */
trait HasRolesAndPermissions
{
    /** @var array<int,array<int,string>> role id => permission names (per process) */
    private static array $permissionCache = [];

    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** Super Admins (the Admin flag) pass every check. Everyone else gets what their role grants. */
    public function hasPermission(string $permission): bool
    {
        return $this->isAdmin() || in_array($permission, $this->permissionNames(), true);
    }

    /** @return array<int,string> */
    public function permissionNames(): array
    {
        try {
            $roleId = $this->role_id ?: Role::idForKey(Role::DEFAULT_KEY);

            if (! $roleId) {
                return [];
            }

            return self::$permissionCache[$roleId] ??= RolePermission::query()->where('role_id', $roleId)->pluck('permission')->all();
        } catch (Throwable) {
            return [];   // tables not created yet
        }
    }

    public function roleLabel(): string
    {
        if ($this->isAdmin()) {
            return 'Super Admin';
        }

        try {
            return $this->accessRole?->name ?? (Role::query()->where('key', Role::DEFAULT_KEY)->value('name') ?? 'User');
        } catch (Throwable) {
            return 'User';
        }
    }

    public static function flushPermissionCache(): void
    {
        self::$permissionCache = [];
        Role::flushCache();
    }
}

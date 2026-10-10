<?php

namespace App\Modules\Access;

use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\RolePermission;
use App\Support\Modules\Module as BaseModule;

/**
 * Roles & permissions. Every module declares permissions; roles collect them; users get one role.
 * "Super Admin" is the existing Admin flag on a user: it always has every permission.
 * Users without a role behave like "Campaign Manager".
 */
class Module extends BaseModule
{
    public function key(): string
    {
        return 'access';
    }

    public function name(): string
    {
        return 'Roles & Permissions';
    }

    public function description(): string
    {
        return 'Roles (Admin, Manager, Campaign Manager, Content Manager, Viewer, custom) and a permission matrix every module plugs into.';
    }

    public function icon(): string
    {
        return 'shield-check';
    }

    public function core(): bool
    {
        return true;
    }

    public function permissions(): array
    {
        return ['access.manage' => 'Create roles, change role permissions and assign roles to users'];
    }

    public function systemRoles(): array
    {
        return [
            'admin' => ['name' => 'Admin', 'description' => 'Runs the system day to day. Cannot manage modules or roles.'],
            'manager' => ['name' => 'Manager', 'description' => 'Oversees campaigns, contacts and reports.'],
            'campaign_manager' => ['name' => 'Campaign Manager', 'description' => 'Creates and sends campaigns. Default role for users without one.'],
            'content_manager' => ['name' => 'Content Manager', 'description' => 'Writes templates and email content.'],
            'viewer' => ['name' => 'Viewer', 'description' => 'Read-only access to reports.'],
        ];
    }

    public function menu(): array
    {
        return [[
            'label' => 'Roles & access', 'route' => 'admin.access.roles', 'match' => 'admin.access.*',
            'icon' => 'shield-check', 'permission' => 'access.manage', 'group' => 'admin', 'order' => 80,
        ]];
    }

    public function boot(): void
    {
        User::flushPermissionCache();

        $flush = fn () => User::flushPermissionCache();
        RolePermission::saved($flush);
        RolePermission::deleted($flush);
        Role::saved($flush);
        Role::deleted($flush);
    }
}

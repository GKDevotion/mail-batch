<?php

namespace App\Modules\ActivityLog\Observers;

use App\Modules\Access\Models\Role;
use App\Modules\ActivityLog\Services\ActivityLogger;

class RoleObserver
{
    public function created(Role $role): void
    {
        ActivityLogger::log('access.role_created', "Created role “{$role->name}”", $role);
    }

    public function updated(Role $role): void
    {
        $fields = array_values(array_diff(array_keys($role->getChanges()), ['updated_at']));

        if ($fields !== []) {
            ActivityLogger::log('access.role_updated', "Updated role “{$role->name}”", $role, ['fields' => $fields]);
        }
    }

    public function deleted(Role $role): void
    {
        ActivityLogger::log('access.role_deleted', "Deleted role “{$role->name}”", null, ['id' => $role->id]);
    }
}

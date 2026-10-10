<?php

namespace App\Modules\ActivityLog\Observers;

use App\Models\User;
use App\Modules\ActivityLog\Services\ActivityLogger;

class UserObserver
{
    public function created(User $user): void
    {
        ActivityLogger::log('user.created', "Created user {$user->email}", $user);
    }

    public function updated(User $user): void
    {
        $changes = array_keys($user->getChanges());

        if (in_array('password', $changes, true)) {
            ActivityLogger::log('user.password_changed', "Password changed for {$user->email}", $user);
        }

        $fields = array_values(array_diff($changes, ['password', 'remember_token', 'updated_at', 'email_verified_at']));

        if ($fields !== []) {
            ActivityLogger::log('user.updated', "Updated user {$user->email}", $user, ['fields' => $fields]);
        }
    }

    public function deleted(User $user): void
    {
        ActivityLogger::log('user.deleted', "Deleted user {$user->email}", null, ['id' => $user->id]);
    }
}

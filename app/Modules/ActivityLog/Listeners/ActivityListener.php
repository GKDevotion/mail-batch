<?php

namespace App\Modules\ActivityLog\Listeners;

use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Support\Modules\Events\ActivityRecorded;
use App\Support\Modules\Events\ModuleToggled;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class ActivityListener
{
    public function login(Login $event): void
    {
        ActivityLogger::log('auth.login', "{$event->user->name} signed in", $event->user, [], $event->user);
    }

    public function logout(Logout $event): void
    {
        if ($event->user) {
            ActivityLogger::log('auth.logout', "{$event->user->name} signed out", $event->user, [], $event->user);
        }
    }

    public function failed(Failed $event): void
    {
        // only the attempted email is kept; the password is never read
        $email = (string) ($event->credentials['email'] ?? '');

        ActivityLogger::log('auth.failed', 'Failed sign-in attempt'.($email !== '' ? " for {$email}" : ''), null, ['email' => $email]);
    }

    public function recorded(ActivityRecorded $event): void
    {
        ActivityLogger::log($event->action, $event->description, $event->subject, $event->properties);
    }

    public function moduleToggled(ModuleToggled $event): void
    {
        ActivityLogger::log('system.module_toggled', 'Module “'.$event->key.'” '.($event->enabled ? 'enabled' : 'disabled'), null, ['module' => $event->key, 'enabled' => $event->enabled]);
    }
}

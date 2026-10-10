<?php

namespace App\Modules\ActivityLog;

use App\Models\Campaign;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\ActivityLog\Console\PruneActivityCommand;
use App\Modules\ActivityLog\Listeners\ActivityListener;
use App\Modules\ActivityLog\Observers\CampaignObserver;
use App\Modules\ActivityLog\Observers\RoleObserver;
use App\Modules\ActivityLog\Observers\SmtpAccountObserver;
use App\Modules\ActivityLog\Observers\UserObserver;
use App\Support\Modules\Events\ActivityRecorded;
use App\Support\Modules\Events\ModuleToggled;
use App\Support\Modules\Module as BaseModule;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * Audit trail: who did what, from which IP/browser. Other modules never call it directly;
 * they fire ActivityRecorded::record(...) and this module writes it.
 */
class Module extends BaseModule
{
    public function key(): string
    {
        return 'activitylog';
    }

    public function name(): string
    {
        return 'Activity Log';
    }

    public function description(): string
    {
        return 'Audit trail of sign-ins, campaigns, SMTP accounts, users and roles with IP and device.';
    }

    public function icon(): string
    {
        return 'journal-text';
    }

    public function permissions(): array
    {
        return ['activity.view' => 'View the activity log'];
    }

    public function defaultPermissions(): array
    {
        return ['admin' => ['activity.view'], 'manager' => ['activity.view']];
    }

    public function menu(): array
    {
        return [[
            'label' => 'Activity log', 'route' => 'admin.activity.index', 'match' => 'admin.activity.*',
            'icon' => 'journal-text', 'permission' => 'activity.view', 'group' => 'admin', 'order' => 70,
        ]];
    }

    public function widgets(): array
    {
        return [['view' => 'activitylog::widgets.recent', 'permission' => 'activity.view', 'col' => 'col-12', 'order' => 90]];
    }

    public function settings(): array
    {
        return [
            'retention_days' => [
                'label' => 'Keep entries for (days)', 'type' => 'number', 'default' => 180,
                'rules' => 'required|integer|min:7|max:3650', 'help' => 'Older entries are deleted daily (needs the scheduler) or with php artisan activitylog:prune.',
            ],
        ];
    }

    public function commands(): array
    {
        return [PruneActivityCommand::class];
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->command('activitylog:prune')->dailyAt('03:45');
    }

    public function boot(): void
    {
        Event::listen(Login::class, [ActivityListener::class, 'login']);
        Event::listen(Logout::class, [ActivityListener::class, 'logout']);
        Event::listen(Failed::class, [ActivityListener::class, 'failed']);
        Event::listen(ActivityRecorded::class, [ActivityListener::class, 'recorded']);
        Event::listen(ModuleToggled::class, [ActivityListener::class, 'moduleToggled']);

        Campaign::observe(CampaignObserver::class);
        User::observe(UserObserver::class);
        SmtpAccount::observe(SmtpAccountObserver::class);

        if (class_exists(Role::class)) {
            Role::observe(RoleObserver::class);
        }
    }
}

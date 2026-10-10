<?php

namespace App\Modules\ActivityLog\Console;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Support\Modules\ModuleManager;
use Illuminate\Console\Command;

class PruneActivityCommand extends Command
{
    protected $signature = 'activitylog:prune {--days= : Keep this many days (default: module setting)}';

    protected $description = 'Delete old activity log entries';

    public function handle(ModuleManager $modules): int
    {
        $days = (int) ($this->option('days') ?: $modules->setting('activitylog', 'retention_days', 180));
        $deleted = ActivityLog::where('created_at', '<', now()->subDays(max(1, $days)))->delete();

        $this->info("Deleted {$deleted} entr".($deleted === 1 ? 'y' : 'ies')." older than {$days} days.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\DailySendCount;
use App\Models\EmailLog;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'mailbatch:prune';

    protected $description = 'Delete old email logs and daily send counters (retention is set in config/mailbatch.php)';

    public function handle(): int
    {
        $logs = EmailLog::query()
            ->where('created_at', '<', now()->subDays((int) config('mailbatch.retention.email_logs_days')))
            ->delete();

        $counts = DailySendCount::query()
            ->where('date', '<', now()->subDays((int) config('mailbatch.retention.daily_counts_days'))->toDateString())
            ->delete();

        $this->info("Deleted {$logs} email log(s) and {$counts} daily counter(s).");

        return self::SUCCESS;
    }
}

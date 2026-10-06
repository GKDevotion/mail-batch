<?php

namespace App\Console\Commands;

use App\Services\QueueWorkerService;
use Illuminate\Console\Command;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Self-stopping queue worker: runs until no jobs are left, then exits.
 * Only one instance works at a time (lease in the cache, renewed while it runs, expires by itself if the process dies).
 */
class _WorkCommand extends Command
{
    protected $signature = 'mailbatch:work {--max-minutes=60 : Safety limit for one run}';

    protected $description = 'Process the MailBatch queue until it is empty, then stop';

    private string $token = '';

    public function handle(QueueWorkerService $workers): int
    {
        $renew = fn () => Cache::put(QueueWorkerService::LEASE_KEY, $this->token, QueueWorkerService::LEASE_SECONDS);
        Event::listen(Looping::class, $renew);
        Event::listen(JobProcessing::class, $renew);
        Event::listen(JobProcessed::class, $renew);

        // A few rounds close the small window in which a new batch is queued while this worker is shutting down.
        for ($round = 0; $round < 3; $round++) {
            $this->token = Str::random(20);

            if (! Cache::add(QueueWorkerService::LEASE_KEY, $this->token, QueueWorkerService::LEASE_SECONDS)) {
                $this->line('Another MailBatch worker is already running.');

                return self::SUCCESS;
            }

            try {
                $this->work($workers);
            } finally {
                if (Cache::get(QueueWorkerService::LEASE_KEY) === $this->token) {
                    Cache::forget(QueueWorkerService::LEASE_KEY);
                }
            }

            if (! $workers->hasWaitingJobs()) {
                break;
            }
        }

        $this->info('Queue is empty. Worker stopped.');

        return self::SUCCESS;
    }

    private function work(QueueWorkerService $workers): void
    {
        $deadline = now()->addMinutes(max(1, (int) $this->option('max-minutes')));
        $passes = 0;

        do {
            $this->call('queue:work', [
                '--queue' => config('mailbatch.queue.name').',default',
                '--stop-when-empty' => true,
                '--tries' => 1,
                '--timeout' => 90,
                '--sleep' => 1,
                '--max-time' => 3000,
            ]);

            Cache::put(QueueWorkerService::LEASE_KEY, $this->token, QueueWorkerService::LEASE_SECONDS);
            sleep(1);
            $passes++;
        } while ($workers->hasWaitingJobs() && now()->lt($deadline) && $passes < 200);
    }
}

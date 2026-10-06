<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Browser-driven queue worker. No `php artisan queue:work`, no Supervisor, no cron.
 *
 * Every campaign has its OWN queue (mailbatch-c{id}) and its own lock, so two campaigns never
 * touch each other's jobs. When the user clicks Start / Retry, the page calls the work URL in a loop;
 * each call processes this campaign's jobs for a few seconds. The loop ends by itself when the
 * campaign's queue is empty. Nothing keeps running afterwards.
 */
class CampaignWorkerService
{
    /** Unique queue name of one campaign. */
    public static function queueName(int $campaignId): string
    {
        return config('mailbatch.queue.name').'-c'.$campaignId;
    }

    /** Is at least one job (batch or email) of this campaign still waiting in its queue? */
    public static function waiting(int $campaignId): bool
    {
        if (config('queue.default') === 'sync') {
            return false;
        }

        try {
            return Queue::size(self::queueName($campaignId)) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Process this campaign's jobs for up to $seconds, then return.
     *
     * @return array{busy:bool, has_more:bool}  busy = another request already works on this campaign
     */
    public function work(Campaign $campaign, ?int $seconds = null): array
    {
        $id = (int) $campaign->id;

        if (config('queue.default') === 'sync') {
            return ['busy' => false, 'has_more' => false];
        }

        $seconds = max(5, min(45, $seconds ?? (int) config('mailbatch.worker.request_seconds', 15)));

        // One worker per campaign. The lock expires by itself if the request dies.
        $lock = Cache::lock('mailbatch:worker:campaign:'.$id, $seconds + 75);

        if (! $lock->get()) {
            return ['busy' => true, 'has_more' => self::waiting($id)];
        }

        // Finish the e-mail that is being sent even if the browser tab is closed meanwhile.
        ignore_user_abort(true);
        @set_time_limit($seconds + 60);

        try {
            $connection = (string) config('queue.default');
            $queue = self::queueName($id);
            $worker = app('queue.worker');
            $options = new WorkerOptions(name: 'default', backoff: 0, memory: 256, timeout: 90, sleep: 1, maxTries: 1, force: true);

            $started = microtime(true);

            while ((microtime(true) - $started) < $seconds && self::waiting($id)) {
                $worker->runNextJob($connection, $queue, $options);
            }

            if (! self::waiting($id)) {
                app(CampaignService::class)->finishBatchIfIdle($id);   // set the final status when nothing is left
            }
        } finally {
            $lock->release();
        }

        return ['busy' => false, 'has_more' => self::waiting($id)];
    }
}

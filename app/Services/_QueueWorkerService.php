<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

/**
 * Starts ONE background queue worker on demand (when the user clicks Start / Retry).
 *
 * The worker is the `mailbatch:work` command: it takes a short-lived lease (so two workers never run
 * side by side), processes jobs until the queue is empty and then exits. Nothing keeps running afterwards.
 *
 * On production servers that already run Supervisor, set MAILBATCH_AUTO_WORKER=false.
 */
class _QueueWorkerService
{
    public const LEASE_KEY = 'mailbatch:worker:lease';

    public const LEASE_SECONDS = 90;

    /** @return 'started'|'running'|'disabled'|'unavailable' */
    public function launch(): string
    {
        if (app()->runningUnitTests() || config('queue.default') === 'sync' || ! config('mailbatch.worker.auto_start')) {
            return 'disabled';
        }

        if (Cache::has(self::LEASE_KEY)) {
            return 'running';   // the active worker re-checks the queue before it exits
        }

        $php = config('mailbatch.worker.php_binary') ?: (new PhpExecutableFinder())->find(false);

        if (! $php) {
            return 'unavailable';
        }

        $php = escapeshellarg($php);
        $artisan = escapeshellarg(base_path('artisan'));

        if (PHP_OS_FAMILY === 'Windows') {
            if (! $this->callable('popen')) {
                return 'unavailable';
            }

            $handle = @popen("start /B \"\" {$php} {$artisan} mailbatch:work > NUL 2>&1", 'r');

            if ($handle === false) {
                return 'unavailable';
            }

            pclose($handle);
        } else {
            if (! $this->callable('exec')) {
                return 'unavailable';
            }

            exec("nohup {$php} {$artisan} mailbatch:work > /dev/null 2>&1 &");
        }

        return 'started';
    }

    public function hasWaitingJobs(): bool
    {
        try {
            return (Queue::size((string) config('mailbatch.queue.name')) + Queue::size('default')) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function callable(string $function): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists($function) && ! in_array($function, $disabled, true);
    }
}

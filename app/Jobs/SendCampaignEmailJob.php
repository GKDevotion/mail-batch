<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\CampaignProgressService;
use App\Services\CampaignService;
use App\Services\CampaignWorkerService;
use App\Services\EmailSendingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One email. tries = 1: a job is never automatically repeated, because a repeat could send twice.
 * Re-sending is always an explicit user action ("Retry failed").
 * The job only carries the recipient id; the SMTP password is read inside the worker and never serialised.
 *
 * Uniqueness: a recipient can be queued only once because the batch "claims" it (queued_at) under a row lock,
 * and only one worker can send it (cache lock + row lock + sending_at).
 */
class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $recipientId, public readonly ?int $campaignId = null)
    {
        $this->tries = max(1, (int) config('mailbatch.queue.job_tries'));
        $this->timeout = (int) config('mailbatch.queue.job_timeout');

        // One queue per campaign, so campaigns never block or mix with each other.
        $this->onQueue(CampaignWorkerService::queueName(
            $campaignId ?? (int) CampaignRecipient::query()->whereKey($recipientId)->value('campaign_id')
        ));
    }

    public function handle(EmailSendingService $sender, CampaignService $campaigns, CampaignProgressService $progress): void
    {
        // Second protection layer next to the database row lock: one worker per recipient.
        $lock = Cache::lock('mailbatch:recipient:'.$this->recipientId, 180);

        if (! $lock->get()) {
            return;
        }

        $campaignId = null;
        $outcome = null;

        try {
            $recipient = CampaignRecipient::find($this->recipientId);

            if (! $recipient) {
                return;
            }

            $campaignId = $recipient->campaign_id;
            $campaign = Campaign::with(['smtpAccount', 'user'])->find($campaignId);

            if (! $campaign) {
                return;
            }

            $result = $sender->send($campaign, $recipient);
            $outcome = $result['outcome'];

            if ($outcome === 'systemic') {
                $campaigns->pauseForError($campaignId, (string) $result['message']);
            }

            $progress->refreshCounters($campaign);
        } finally {
            $lock->release();

            if ($campaignId) {
                $campaigns->finishBatchIfIdle($campaignId);
            }
        }

        $this->pace($outcome, $campaignId);
    }

    /** Small gap between real sends (provider friendliness). Skipped after the last email and when nothing was attempted. */
    private function pace(?string $outcome, ?int $campaignId): void
    {
        $gap = (int) config('mailbatch.queue.delay_between_emails_seconds');

        if ($gap <= 0 || app()->runningUnitTests() || ! in_array($outcome, ['sent', 'failed'], true)) {
            return;
        }

        if ($campaignId && CampaignRecipient::query()->where('campaign_id', $campaignId)->whereNotNull('queued_at')->exists()) {
            sleep(min($gap, 10));
        }
    }

    /** Timeout, killed worker or unexpected exception. */
    public function failed(Throwable $e): void
    {
        $row = CampaignRecipient::find($this->recipientId);

        if (! $row) {
            return;
        }

        if ($row->sent_at === null && $row->sending_at !== null) {
            $row->update([
                'error_message' => EmailSendingService::INTERRUPTED,
                'retry_count' => $row->retry_count + 1,
                'sending_at' => null,
            ]);
        }

        $row->update(['queued_at' => null]);

        Log::error('Send job failed', [
            'campaign_id' => $row->campaign_id, 'recipient_id' => $row->id, 'error_type' => $e::class,
        ]);

        app(CampaignService::class)->finishBatchIfIdle($row->campaign_id);
    }
}

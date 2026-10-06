<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\SystemSetting;
use App\Jobs\ProcessCampaignBatchJob;
use App\Jobs\SendCampaignEmailJob;
use App\Models\CampaignRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use DomainException;
use Illuminate\Support\Facades\Storage;

class CampaignService
{
    public function __construct(private readonly CampaignProgressService $progress)
    {
    }

    /** Admin-configurable ceiling (system setting), falling back to config/.env. */
    public static function maxBatchSize(): int
    {
        return max(1, min((int) config('mailbatch.hard_max_batch_size'), (int) SystemSetting::get('max_batch_size', config('mailbatch.max_batch_size'))));
    }

    public function create(User $user, array $data): Campaign
    {
        return Campaign::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'batch_size' => min((int) $data['batch_size'], self::maxBatchSize()),
            'include_unsubscribe' => (bool) ($data['include_unsubscribe'] ?? false),
            'sender_identification' => $data['sender_identification'] ?? null,
            'status' => CampaignStatus::Draft,
        ]);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        $campaign->update([
            'name' => $data['name'],
            'batch_size' => min((int) $data['batch_size'], self::maxBatchSize()),
            'include_unsubscribe' => (bool) ($data['include_unsubscribe'] ?? false),
            'sender_identification' => $data['sender_identification'] ?? null,
        ]);

        return $campaign;
    }

    public function delete(Campaign $campaign): void
    {
        if ($campaign->status === CampaignStatus::Processing) {
            throw new DomainException('A campaign that is currently sending cannot be deleted. Pause it first.');
        }

        if ($campaign->excel_path) {
            Storage::disk(config('mailbatch.upload.disk'))->delete($campaign->excel_path);
        }

        $campaign->delete(); // recipients and email logs cascade
    }

    /**
     * Wizard checklist shown on the campaign page. `route` links are only rendered
     * once the matching route exists (added in later phases).
     *
     * @return array<int, array{key:string,label:string,route:string,done:bool,phase:int}>
     */
    public function wizardSteps(Campaign $campaign): array
    {
        return [
            ['key' => 'upload', 'label' => 'Upload Excel & preview', 'route' => 'campaigns.preview',
                'done' => filled($campaign->excel_path), 'phase' => 3],
            ['key' => 'mapping', 'label' => 'Map columns', 'route' => 'campaigns.mapping',
                'done' => ! empty($campaign->column_mapping['email'] ?? null), 'phase' => 3],
            ['key' => 'smtp', 'label' => 'Configure SMTP', 'route' => 'campaigns.smtp',
                'done' => (bool) $campaign->smtp_account_id, 'phase' => 4],
            ['key' => 'compose', 'label' => 'Compose email', 'route' => 'campaigns.compose',
                'done' => filled($campaign->subject) && filled($campaign->body_html), 'phase' => 5],
            ['key' => 'send', 'label' => 'Confirm & send', 'route' => 'campaigns.confirm',
                'done' => in_array($campaign->status, [CampaignStatus::Processing, CampaignStatus::Completed], true), 'phase' => 6],
        ];
    }

    // ------------------------------------------------------------------ sending lifecycle

    /**
     * Claim the campaign and queue ONE batch. Mode "pending" = first sends, "retry" = failed rows.
     * Row-locked so a double click cannot start two batches.
     *
     * @return int number of emails the batch may send (at most the batch size / daily limit)
     *
     * @throws DomainException with a user-friendly message
     */
    public function startBatch(Campaign $campaign, string $mode = 'pending'): int
    {
        if (config('queue.default') === 'sync' && app()->isProduction()) {
            throw new DomainException('The queue driver is "sync". Set QUEUE_CONNECTION=database before sending.');
        }

        [$locked, $count] = DB::transaction(function () use ($campaign, $mode): array {
            $c = Campaign::query()->with(['smtpAccount', 'user'])->lockForUpdate()->findOrFail($campaign->id);

            if ($c->status === CampaignStatus::Processing) {
                throw new DomainException('A batch is already running. Wait for it to finish or pause the campaign.');
            }

            if (! $c->isConfigured()) {
                throw new DomainException('Finish the setup first: upload and map the Excel file, choose an SMTP account and write the email.');
            }

            if (! $c->smtpAccount?->isActive()) {
                throw new DomainException('The selected SMTP account is not available. Choose another one.');
            }

            $this->progress->recoverStale($c);

            if ($this->progress->queuedCount($c) > 0) {
                throw new DomainException('The previous batch is still finishing. Try again in a moment.');
            }

            $available = $mode === 'retry' ? $this->progress->retryableCount($c) : $this->progress->pendingCount($c);

            if ($available === 0) {
                throw new DomainException($mode === 'retry'
                    ? 'No failed emails can be retried (the retry limit may have been reached).'
                    : 'No pending recipients are left to send.');
            }

            $remainingToday = $this->progress->dailyRemaining($c->user);

            if ($remainingToday <= 0) {
                throw new DomainException('Your daily sending limit ('.number_format($c->user->effectiveDailyLimit()).') has been reached. Try again tomorrow.');
            }

            $c->update([
                'status' => CampaignStatus::Processing,
                'started_at' => $c->started_at ?? now(),
                'completed_at' => null,
                'last_error' => null,
            ]);

            return [$c, min($available, $c->batch_size, self::maxBatchSize(), $remainingToday)];
        });

        ProcessCampaignBatchJob::dispatch($locked->id, $mode);

        return $count;
    }

    public function pause(Campaign $campaign): void
    {
        Campaign::query()->whereKey($campaign->id)
            ->where('status', CampaignStatus::Processing->value)
            ->update(['status' => CampaignStatus::Paused->value, 'last_error' => null]);
        // Jobs that have not started yet see the new status and release their rows.
    }

    /** The SMTP side is broken (auth, connection, limits): stop and tell the user why. */
    public function pauseForError(int $campaignId, string $message): void
    {
        Campaign::query()->whereKey($campaignId)
            ->where('status', CampaignStatus::Processing->value)
            ->update(['status' => CampaignStatus::Paused->value, 'last_error' => $message]);
    }

    public function failCampaign(int $campaignId, string $message): void
    {
        Campaign::query()->whereKey($campaignId)
            ->update(['status' => CampaignStatus::Failed->value, 'last_error' => $message]);

        CampaignRecipient::query()->where('campaign_id', $campaignId)->whereNull('sending_at')->update(['queued_at' => null]);
    }

    /**
     * Called after every email job. When no recipient is still queued, recount and set the final status:
     * Completed (nothing left to send) or Ready (waiting for the user to start the next batch).
     * Paused / Failed campaigns keep their status. Safe to call repeatedly.
     */
    public function finishBatchIfIdle(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId) {
            $c = Campaign::query()->lockForUpdate()->find($campaignId);

            if (! $c) {
                return;
            }

            $this->progress->recoverStale($c);

            if ($this->progress->queuedCount($c) > 0) {
                return; // batch still running
            }

            $this->progress->refreshCounters($c);

            if ($c->status !== CampaignStatus::Processing) {
                return;
            }

            if ($this->progress->pendingCount($c) > 0) {
                $c->update(['status' => CampaignStatus::Ready]);   // waiting for the next batch
            } else {
                $c->update(['status' => CampaignStatus::Completed, 'completed_at' => now()]);
            }
        });
    }

    /**
     * Queue ONE failed recipient again. Refused for sent rows, skipped rows, rows at the retry cap,
     * rows already queued, and while a batch is running (so the batch size is never exceeded).
     *
     * @throws DomainException with a user-friendly message
     */
    public function retryRecipient(Campaign $campaign, CampaignRecipient $recipient): void
    {
        if (config('queue.default') === 'sync' && app()->isProduction()) {
            throw new DomainException('The queue driver is "sync". Set QUEUE_CONNECTION=database before sending.');
        }

        DB::transaction(function () use ($campaign, $recipient) {
            $c = Campaign::query()->with(['smtpAccount', 'user'])->lockForUpdate()->findOrFail($campaign->id);

            if ($c->status === CampaignStatus::Processing) {
                throw new DomainException('A batch is running. Wait for it to finish, then retry.');
            }

            if (! $c->isConfigured() || ! $c->smtpAccount?->isActive()) {
                throw new DomainException('Choose an active SMTP account and finish the email template before retrying.');
            }

            $this->progress->recoverStale($c);

            $r = CampaignRecipient::query()->where('campaign_id', $c->id)->whereKey($recipient->id)->lockForUpdate()->first();

            if (! $r) {
                throw new DomainException('Recipient not found.');
            }

            if ($r->alreadySent()) {
                throw new DomainException('This email was already sent and will not be sent again.');
            }

            if ($r->retry_count >= (int) config('mailbatch.max_retries')) {
                throw new DomainException('The retry limit ('.config('mailbatch.max_retries').') has been reached for this recipient.');
            }

            if ($r->error_message === null || ! $r->canRetry()) {
                throw new DomainException('Only failed emails can be retried.');
            }

            if ($r->queued_at !== null || $r->sending_at !== null) {
                throw new DomainException('This email is already queued.');
            }

            if ($this->progress->dailyRemaining($c->user) <= 0) {
                throw new DomainException('Your daily sending limit has been reached. Try again tomorrow.');
            }

            $r->update(['queued_at' => now()]);
            $c->update(['status' => CampaignStatus::Processing, 'completed_at' => null, 'last_error' => null]);
        });

        SendCampaignEmailJob::dispatch($recipient->id, $campaign->id);
    }
}

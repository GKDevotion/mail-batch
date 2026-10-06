<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\CampaignProgressService;
use App\Services\CampaignService;
use App\Services\CampaignWorkerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Claims at most one batch (<= batch size, <= system max, <= daily remaining) and fans out
 * one SendCampaignEmailJob per recipient. mode: "pending" (first sends) or "retry" (failed ones).
 */
class ProcessCampaignBatchJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /** Never two identical batch jobs for the same campaign and mode in the queue at once. */
    public int $uniqueFor = 60;

    public function uniqueId(): string
    {
        return $this->campaignId.':'.$this->mode;
    }

    public function __construct(public readonly int $campaignId, public readonly string $mode = 'pending')
    {
        $this->onQueue(CampaignWorkerService::queueName($campaignId));   // one queue per campaign
    }

    public function handle(CampaignProgressService $progress, CampaignService $campaigns): void
    {
        $campaign = Campaign::with(['smtpAccount', 'user'])->find($this->campaignId);

        if (! $campaign || $campaign->status !== CampaignStatus::Processing) {
            return; // paused/cancelled before the worker got here
        }

        if (! $campaign->smtpAccount?->isActive()) {
            $campaigns->failCampaign($campaign->id, 'No active SMTP account is selected for this campaign.');

            return;
        }

        $limit = min($campaign->batch_size, CampaignService::maxBatchSize(), $progress->dailyRemaining($campaign->user));

        if ($limit <= 0) {
            $campaigns->pauseForError($campaign->id, 'Daily sending limit reached. Resume tomorrow or ask an administrator to raise your limit.');

            return;
        }

        $ids = DB::transaction(function () use ($campaign, $limit) {
            $query = CampaignRecipient::query()->where('campaign_id', $campaign->id)->queueable();
            $query = $this->mode === 'retry' ? $query->retryable() : $query->pending();

            $ids = $query->orderBy('row_number')->limit($limit)->lockForUpdate()->pluck('id');

            if ($ids->isNotEmpty()) {
                CampaignRecipient::query()->whereIn('id', $ids)->update(['queued_at' => now()]);
            }

            return $ids;
        });

        if ($ids->isEmpty()) {
            $campaigns->finishBatchIfIdle($campaign->id);

            return;
        }

        // No delayed jobs: the worker paces itself, so the browser-driven worker never stops with work left.
        foreach ($ids->values() as $recipientId) {
            SendCampaignEmailJob::dispatch((int) $recipientId, $campaign->id);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Batch job failed', ['campaign_id' => $this->campaignId, 'error_type' => $e::class]);

        app(CampaignService::class)->failCampaign($this->campaignId, 'The batch could not be started. Please try again.');
    }
}

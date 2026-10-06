<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\DailySendCount;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CampaignProgressService
{
    /**
     * Dashboard totals for one user, computed in a single aggregate query.
     * State rules mirror CampaignRecipient::state (skipped > sent > failed > pending).
     *
     * @return array<string,int>
     */
    public function userTotals(User $user): array
    {
        $r = 'campaign_recipients';
        $unsent = "COALESCE($r.status, 0) = 0 AND $r.sent_at IS NULL AND $r.skip_reason IS NULL";

        $row = CampaignRecipient::query()
            ->join('campaigns', 'campaigns.id', '=', "$r.campaign_id")
            ->where('campaigns.user_id', $user->id)
            ->selectRaw("
                COUNT(*) AS total,
                COALESCE(SUM($r.skip_reason IS NOT NULL), 0) AS skipped,
                COALESCE(SUM($r.skip_reason IS NULL AND ($r.status = 1 OR $r.sent_at IS NOT NULL)), 0) AS sent,
                COALESCE(SUM($unsent AND $r.error_message IS NOT NULL), 0) AS failed,
                COALESCE(SUM($unsent AND $r.error_message IS NULL), 0) AS pending
            ")
            ->first();

        return [
            'total_campaigns' => Campaign::where('user_id', $user->id)->count(),
            'total_recipients' => (int) ($row->total ?? 0),
            'sent' => (int) ($row->sent ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'skipped' => (int) ($row->skipped ?? 0),
        ];
    }

    /**
     * Per-day figures for the dashboard sparklines and chart (oldest day first, missing days = 0).
     *
     * @return array{labels:array<int,string>, campaigns:array<int,int>, recipients:array<int,int>, sent:array<int,int>, failed:array<int,int>}
     */
    public function dailySeries(User $user, int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $keys = [];
        $labels = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $keys[] = $day->toDateString();
            $labels[] = $day->format('d M');
        }

        $fill = function ($rows) use ($keys): array {
            $map = collect($rows)->pluck('c', 'd')->all();

            return array_map(fn (string $k) => (int) ($map[$k] ?? 0), $keys);
        };

        $campaigns = Campaign::query()
            ->where('user_id', $user->id)->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) AS d, COUNT(*) AS c')->groupBy('d')->get();

        $recipients = CampaignRecipient::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_recipients.campaign_id')
            ->where('campaigns.user_id', $user->id)->where('campaign_recipients.created_at', '>=', $start)
            ->selectRaw('DATE(campaign_recipients.created_at) AS d, COUNT(*) AS c')->groupBy('d')->get();

        $failed = EmailLog::query()
            ->join('campaigns', 'campaigns.id', '=', 'email_logs.campaign_id')
            ->where('campaigns.user_id', $user->id)->where('email_logs.status', 'failed')->where('email_logs.created_at', '>=', $start)
            ->selectRaw('DATE(email_logs.created_at) AS d, COUNT(*) AS c')->groupBy('d')->get();

        $sentMap = DailySendCount::query()
            ->where('user_id', $user->id)->where('date', '>=', $start->toDateString())
            ->get(['date', 'sent'])
            ->mapWithKeys(fn ($r) => [substr((string) $r->date, 0, 10) => (int) $r->sent])->all();

        return [
            'labels' => $labels,
            'campaigns' => $fill($campaigns),
            'recipients' => $fill($recipients),
            'sent' => array_map(fn (string $k) => (int) ($sentMap[$k] ?? 0), $keys),
            'failed' => $fill($failed),
        ];
    }

    /** @return array{total:int,sent:int,failed:int,skipped:int,pending:int} */
    public function counts(Campaign $campaign): array
    {
        $unsent = 'COALESCE(status, 0) = 0 AND sent_at IS NULL AND skip_reason IS NULL';

        $row = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(*) AS total,
                COALESCE(SUM(skip_reason IS NOT NULL), 0) AS skipped,
                COALESCE(SUM(skip_reason IS NULL AND (status = 1 OR sent_at IS NOT NULL)), 0) AS sent,
                COALESCE(SUM($unsent AND error_message IS NOT NULL), 0) AS failed,
                COALESCE(SUM($unsent AND error_message IS NULL), 0) AS pending
            ")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'sent' => (int) ($row->sent ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'skipped' => (int) ($row->skipped ?? 0),
            'pending' => (int) ($row->pending ?? 0),
        ];
    }

    /** Authoritative recount into the cached campaign columns (idempotent; avoids racy increments). */
    public function refreshCounters(Campaign $campaign): array
    {
        $c = $this->counts($campaign);

        Campaign::query()->whereKey($campaign->id)->update([
            'sent_count' => $c['sent'], 'failed_count' => $c['failed'], 'skipped_count' => $c['skipped'],
        ]);

        return $c;
    }

    public function queuedCount(Campaign $campaign): int
    {
        return CampaignRecipient::query()->where('campaign_id', $campaign->id)->whereNotNull('queued_at')->count();
    }

    public function pendingCount(Campaign $campaign): int
    {
        return CampaignRecipient::query()->where('campaign_id', $campaign->id)->queueable()->pending()->count();
    }

    public function retryableCount(Campaign $campaign): int
    {
        return CampaignRecipient::query()->where('campaign_id', $campaign->id)->queueable()->retryable()->count();
    }

    /**
     * Clean up after crashed/lost jobs:
     *  - attempts stuck "sending" too long: marked failed with an "unknown result" message (never auto re-sent)
     *  - rows claimed by a batch but never picked up: released
     */
    public function recoverStale(Campaign $campaign): void
    {
        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->whereNull('sent_at')
            ->where('sending_at', '<', now()->subMinutes((int) config('mailbatch.queue.stale_sending_minutes', 15)))
            ->update([
                'error_message' => EmailSendingService::INTERRUPTED,
                'retry_count' => DB::raw('retry_count + 1'),
                'sending_at' => null,
                'queued_at' => null,
            ]);

        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->whereNull('sending_at')
            ->where('queued_at', '<', now()->subMinutes((int) config('mailbatch.queue.stale_queued_minutes', 30)))
            ->update(['queued_at' => null]);
    }

    public function sentToday(User $user): int
    {
        return DailySendCount::today($user->id);
    }

    /** Daily limit minus emails already sent today minus emails currently queued. */
    public function dailyRemaining(User $user): int
    {
        $queued = CampaignRecipient::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_recipients.campaign_id')
            ->where('campaigns.user_id', $user->id)
            ->whereNotNull('campaign_recipients.queued_at')
            ->count();

        return max(0, $user->effectiveDailyLimit() - $this->sentToday($user) - $queued);
    }

    /** Latest real send attempts (test emails excluded) for the live activity feed. */
    public function recent(Campaign $campaign, int $limit = 8): array
    {
        return EmailLog::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', ['sent', 'failed'])
            ->latest('id')
            ->limit($limit)
            ->get(['email', 'status', 'error_message', 'created_at'])
            ->map(fn ($l) => [
                'email' => $l->email,
                'status' => $l->status,
                'error' => $l->error_message,
                'time' => $l->created_at?->format('H:i:s'),
            ])->all();
    }

    /** @return array<string,mixed> */
    public function snapshot(Campaign $campaign): array
    {
        $campaign = $campaign->fresh(['user']);
        $c = $this->counts($campaign);
        $processed = $c['sent'] + $c['failed'] + $c['skipped'];
        $running = $campaign->status === CampaignStatus::Processing;

        return [
            'status' => $campaign->status->value,
            'status_label' => $campaign->status->label(),
            'badge' => $campaign->status->badgeClass(),
            'total' => $c['total'],
            'eligible' => $campaign->eligible_records,
            'processed' => $processed,
            'sent' => $c['sent'],
            'failed' => $c['failed'],
            'skipped' => $c['skipped'],
            'remaining' => $c['pending'],
            'percent' => $c['total'] > 0 ? min(100, (int) round($processed / $c['total'] * 100)) : 0,
            'queued' => $this->queuedCount($campaign),
            'queue_waiting' => CampaignWorkerService::waiting($campaign->id),   // page keeps calling the work URL while true
            'is_running' => $running,
            'can_start' => ! $running && $c['pending'] > 0 && $campaign->isConfigured(),
            'can_pause' => $running,
            'can_retry' => ! $running && $this->retryableCount($campaign) > 0,
            'last_error' => $campaign->last_error,
            'daily_remaining' => $this->dailyRemaining($campaign->user),
            'recent' => $this->recent($campaign),
        ];
    }
}

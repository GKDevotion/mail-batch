<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CampaignProgressService;
use App\Services\CampaignService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignSendController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignProgressService $progress,
    ) {
    }

    public function confirm(Request $request, Campaign $campaign): View|RedirectResponse
    {
        $this->authorize('view', $campaign);

        if (! $campaign->excel_path || empty($campaign->column_mapping['email'] ?? null)) {
            return redirect()->route('campaigns.preview', $campaign)->with('error', 'Upload and map your Excel file first.');
        }

        $campaign->load('smtpAccount');

        return view('campaigns.confirm', [
            'campaign' => $campaign,
            'snapshot' => $this->progress->snapshot($campaign),
            'reasons' => $campaign->recipients()->whereNotNull('skip_reason')
                ->selectRaw('skip_reason, COUNT(*) AS c')->groupBy('skip_reason')->pluck('c', 'skip_reason'),
            'canSend' => $request->user()->can('send', $campaign),
            'dailyLimit' => $request->user()->effectiveDailyLimit(),
            'sentToday' => $this->progress->sentToday($request->user()),
        ]);
    }

    /** AJAX: queue the next batch (maximum batch size per click). */
    public function start(Campaign $campaign): JsonResponse
    {
        $this->authorize('send', $campaign);

        return $this->run(fn () => $this->campaigns->startBatch($campaign, 'pending'), $campaign, 'Batch started');
    }

    /** AJAX: re-queue failed recipients (never recipients with status 1; capped by retry counter). */
    public function retryFailed(Campaign $campaign): JsonResponse
    {
        $this->authorize('send', $campaign);

        return $this->run(fn () => $this->campaigns->startBatch($campaign, 'retry'), $campaign, 'Retry started');
    }

    public function pause(Campaign $campaign): JsonResponse
    {
        $this->authorize('send', $campaign);

        $this->campaigns->pause($campaign);

        return response()->json([
            'ok' => true,
            'message' => 'Campaign paused. Emails already being sent will finish; the rest are released.',
            'snapshot' => $this->progress->snapshot($campaign),
        ]);
    }

    private function run(callable $action, Campaign $campaign, string $label): JsonResponse
    {
        try {
            $count = $action();
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => "{$label}: up to {$count} email(s) queued. Sending runs from this page and stops by itself when done.",
            'worker' => 'browser',
            'work_url' => route('campaigns.work', $campaign),
            'snapshot' => $this->progress->snapshot($campaign),
        ]);
    }
}

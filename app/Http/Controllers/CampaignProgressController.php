<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CampaignProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignProgressController extends Controller
{
    public function __construct(private readonly CampaignProgressService $progress)
    {
    }

    public function show(Request $request, Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        return view('campaigns.progress', [
            'campaign' => $campaign->load('smtpAccount'),
            'snapshot' => $this->progress->snapshot($campaign),
            'canSend' => $request->user()->can('send', $campaign),
        ]);
    }

    /** AJAX polling endpoint (counters, status, recent activity). */
    public function status(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        return response()->json($this->progress->snapshot($campaign));
    }

    /** Email delivery log: every attempt (sent / failed / test) with a safe error message. */
    public function logs(Request $request, Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        $status = in_array($request->string('status')->toString(), ['sent', 'failed', 'test'], true)
            ? $request->string('status')->toString() : null;
        $search = trim($request->string('q')->toString());

        $logs = $campaign->emailLogs()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('email', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('campaigns.logs', [
            'campaign' => $campaign,
            'logs' => $logs,
            'status' => $status,
            'search' => $search,
            'totals' => $campaign->emailLogs()->selectRaw('status, COUNT(*) AS c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }
}

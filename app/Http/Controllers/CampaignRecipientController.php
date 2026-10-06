<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\CampaignProgressService;
use App\Services\CampaignService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class CampaignRecipientController extends Controller
{
    private const STATES = ['pending', 'sent', 'failed', 'skipped'];

    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignProgressService $progress,
    ) {
    }

    public function index(Request $request, Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        $state = in_array($request->string('state')->toString(), self::STATES, true) ? $request->string('state')->toString() : null;
        $search = trim($request->string('q')->toString());

        $recipients = $campaign->recipients()
            ->when($state, fn ($q) => $q->inState($state))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('email', 'like', $like)->orWhere('name', 'like', $like)->orWhere('website', 'like', $like));
            })
            ->orderBy('row_number')
            ->paginate(25)
            ->withQueryString();

        return view('campaigns.recipients', [
            'campaign' => $campaign,
            'recipients' => $recipients,
            'state' => $state,
            'search' => $search,
            'counts' => $this->progress->counts($campaign),
            'canSend' => $request->user()->can('send', $campaign),
        ]);
    }

    /** AJAX: fresh table rows (server-rendered HTML) for recipients that are being sent. */
    public function state(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $ids = collect(Arr::wrap($request->input('ids', [])))
            ->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v)->unique()->take(100)->all();

        $canSend = $request->user()->can('send', $campaign);

        $rows = $campaign->recipients()->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (CampaignRecipient $r) => [$r->id => $this->renderRow($campaign, $r, $canSend)]);

        return response()->json(['rows' => $rows]);
    }

    /** AJAX: retry ONE failed recipient (never a sent one; capped by the retry counter). */
    public function retry(Request $request, Campaign $campaign, CampaignRecipient $recipient): JsonResponse
    {
        $this->authorize('send', $campaign);
        abort_unless($recipient->campaign_id === $campaign->id, 404);

        try {
            $this->campaigns->retryRecipient($campaign, $recipient);
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'worker' => 'browser',
            'message' => 'Retry queued for '.$recipient->email.'.',
            'row' => $this->renderRow($campaign, $recipient->fresh(), true),
        ]);
    }

    private function renderRow(Campaign $campaign, CampaignRecipient $recipient, bool $canSend): string
    {
        return view('campaigns.partials.recipient-row', compact('campaign', 'recipient', 'canSend'))->render();
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Campaign::class);

        $search = trim($request->string('q')->toString());
        $status = CampaignStatus::tryFrom($request->string('status')->toString());

        $items = $request->user()->campaigns()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('campaigns.index', [
            'campaigns' => $items,
            'search' => $search,
            'status' => $status?->value,
            'statuses' => CampaignStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        return view('campaigns.create', ['maxBatch' => CampaignService::maxBatchSize()]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $this->authorize('create', Campaign::class);

        $campaign = $this->campaigns->create($request->user(), $request->validated());

        return redirect()->route('campaigns.show', $campaign)
            ->with('status', 'Campaign created. Next step: upload your Excel file.');
    }

    public function show(Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        return view('campaigns.show', [
            'campaign' => $campaign->load('smtpAccount:id,name,from_email'),
            'steps' => $this->campaigns->wizardSteps($campaign),
        ]);
    }

    public function edit(Campaign $campaign): View
    {
        $this->authorize('configure', $campaign);

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'maxBatch' => CampaignService::maxBatchSize(),
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('configure', $campaign);

        $this->campaigns->update($campaign, $request->validated());

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Campaign updated.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        try {
            $this->campaigns->delete($campaign);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('campaigns.index')->with('status', 'Campaign deleted.');
    }
}

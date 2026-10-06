<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\CampaignProgressService;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly CampaignProgressService $progress,
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $status = CampaignStatus::tryFrom($request->string('status')->toString());

        return view('admin.campaigns.index', [
            'campaigns' => Campaign::with('user:id,name,email')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->when($status, fn ($q) => $q->where('status', $status->value))
                ->latest()->paginate(20)->withQueryString(),
            'search' => $search,
            'status' => $status?->value,
            'statuses' => CampaignStatus::cases(),
        ]);
    }

    public function show(Campaign $campaign): View
    {
        $campaign->load(['user:id,name,email', 'smtpAccount:id,name,smtp_host,smtp_port,from_email']);

        return view('admin.campaigns.show', [
            'campaign' => $campaign,
            'snapshot' => $this->progress->snapshot($campaign),
            'logs' => $campaign->emailLogs()->latest('id')->limit(10)->get(),
        ]);
    }

    public function pause(Campaign $campaign): RedirectResponse
    {
        $this->campaigns->pause($campaign);

        return back()->with('status', 'Campaign paused.');
    }
}

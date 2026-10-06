<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCampaignSmtpRequest;
use App\Models\Campaign;
use App\Services\SmtpAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class CampaignSmtpController extends Controller
{
    public function __construct(private readonly SmtpAccountService $accounts)
    {
    }

    public function edit(Request $request, Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        return view('campaigns.smtp', [
            'campaign' => $campaign->load('smtpAccount'),
            'accounts' => $request->user()->smtpAccounts()->where('status', 'active')->orderBy('name')->get(),
            'canEdit' => $request->user()->can('configure', $campaign),
        ]);
    }

    public function update(SaveCampaignSmtpRequest $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validated();

        $account = filled($data['smtp_account_id'] ?? null)
            ? $request->user()->smtpAccounts()->findOrFail($data['smtp_account_id'])
            : $this->accounts->create($request->user(), $data);

        $campaign->update(['smtp_account_id' => $account->id]);

        $next = Route::has('campaigns.compose') ? 'campaigns.compose' : 'campaigns.show';

        return redirect()->route($next, $campaign)->with('status', 'SMTP account "'.$account->name.'" selected for this campaign.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\SmtpAccount;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SmtpAccountController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns)
    {
    }

    public function index(): View
    {
        // Passwords are never selected into the view: the column is hidden on the model and unused in the template.
        return view('admin.smtp.index', [
            'accounts' => SmtpAccount::with('user:id,name,email')->withCount('campaigns')->latest()->paginate(20),
        ]);
    }

    public function toggle(SmtpAccount $smtpAccount): RedirectResponse
    {
        $disable = $smtpAccount->isActive();
        $smtpAccount->update(['status' => $disable ? 'disabled' : 'active']);

        if ($disable) {
            Campaign::where('smtp_account_id', $smtpAccount->id)->where('status', 'processing')
                ->each(fn (Campaign $c) => $this->campaigns->pause($c));
        }

        return back()->with('status', 'SMTP account '.($disable ? 'disabled' : 'enabled').'.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->string('status')->toString(), ['sent', 'failed', 'test'], true)
            ? $request->string('status')->toString() : null;
        $search = trim($request->string('q')->toString());

        return view('admin.logs.index', [
            'logs' => EmailLog::with('campaign:id,name')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->where('email', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'search' => $search,
        ]);
    }

    /** Recipients that currently sit in the Failed state, across all campaigns. */
    public function failed(): View
    {
        return view('admin.logs.failed', [
            'recipients' => CampaignRecipient::with(['campaign:id,name,user_id', 'campaign.user:id,name'])
                ->failed()->whereNull('sent_at')->latest('last_attempt_at')->paginate(30),
        ]);
    }
}

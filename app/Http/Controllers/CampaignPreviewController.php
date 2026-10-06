<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignPreviewController extends Controller
{
    public function show(Request $request, Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        return view('campaigns.preview', [
            'campaign' => $campaign,
            'canEdit' => $request->user()->can('update', $campaign),
            'maxMb' => round(config('mailbatch.upload.max_kb') / 1024, 1),
            'maxRows' => (int) config('mailbatch.upload.max_rows'),
        ]);
    }
}

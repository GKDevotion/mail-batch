<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CampaignProgressService;
use App\Services\CampaignWorkerService;
use Illuminate\Http\JsonResponse;

/** URL that replaces `php artisan queue:work`: processes this campaign's own queue for a few seconds. */
class CampaignWorkController extends Controller
{
    public function __construct(
        private readonly CampaignWorkerService $worker,
        private readonly CampaignProgressService $progress,
    ) {
    }

    public function run(Campaign $campaign): JsonResponse
    {
        $this->authorize('send', $campaign);

        $result = $this->worker->work($campaign);

        return response()->json([
            'ok' => true,
            'busy' => $result['busy'],
            'has_more' => $result['has_more'],
            'snapshot' => $this->progress->snapshot($campaign),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\CampaignProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly CampaignProgressService $progress)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'stats' => $this->progress->userTotals($user),
            'series' => $this->progress->dailySeries($user, 14),
            'recentCampaigns' => $user->campaigns()->latest()->limit(5)->get(),
        ]);
    }

    /** AJAX endpoint polled by the dashboard cards. */
    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->progress->userTotals($request->user()));
    }
}

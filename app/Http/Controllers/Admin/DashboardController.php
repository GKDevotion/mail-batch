<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\DailySendCount;
use App\Models\EmailLog;
use App\Models\SmtpAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();

        return view('admin.dashboard', [
            'stats' => [
                'Users' => User::count(),
                'Active users' => User::where('is_active', true)->count(),
                'Campaigns' => Campaign::count(),
                'Sending now' => Campaign::where('status', 'processing')->count(),
                'Sent today' => (int) DailySendCount::where('date', $today)->sum('sent'),
                'Failed today' => EmailLog::where('status', 'failed')->where('created_at', '>=', now()->startOfDay())->count(),
                'Active SMTP accounts' => SmtpAccount::where('status', 'active')->count(),
                'Disabled SMTP accounts' => SmtpAccount::where('status', '!=', 'active')->count(),
                'Queued jobs' => $this->tableCount('jobs'),
                'Failed jobs' => $this->tableCount('failed_jobs'),
            ],
            'recentFailures' => EmailLog::with('campaign:id,name')->where('status', 'failed')->latest('id')->limit(8)->get(),
            'topSenders' => DailySendCount::with('user:id,name,email')->where('date', $today)->orderByDesc('sent')->limit(5)->get(),
        ]);
    }

    private function tableCount(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }
}

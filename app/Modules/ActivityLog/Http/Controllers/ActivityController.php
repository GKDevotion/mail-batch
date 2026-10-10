<?php

namespace App\Modules\ActivityLog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ActivityLog\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $q = trim((string) ($filters['q'] ?? ''));

        $logs = ActivityLog::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('description', 'like', $like)->orWhere('user_name', 'like', $like)
                    ->orWhere('action', 'like', $like)->orWhere('ip', 'like', $like));
            })
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('module', $module))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($from))))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($to))))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('activitylog::index', [
            'logs' => $logs,
            'filters' => $filters + ['q' => '', 'module' => null, 'from' => null, 'to' => null],
            'modules' => ActivityLog::query()->distinct()->orderBy('module')->pluck('module'),
        ]);
    }
}

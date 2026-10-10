@php
    try {
        $recentActivity = \App\Modules\ActivityLog\Models\ActivityLog::query()->latest('id')->limit(6)->get();
    } catch (\Throwable $e) {
        $recentActivity = collect();   // table not created yet (updates not run): dashboard must still load
    }
@endphp
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent activity</span>
        <a href="{{ route('admin.activity.index') }}" class="small fw-normal">View all</a>
    </div>
    <ul class="list-group list-group-flush">
        @forelse ($recentActivity as $entry)
            <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                <span class="min-w-0"><span class="d-block text-break">{{ $entry->description }}</span>
                    <span class="small text-body-secondary">{{ $entry->user_name ?? 'System' }}</span></span>
                <span class="small text-body-secondary text-nowrap">{{ $entry->created_at?->diffForHumans() }}</span>
            </li>
        @empty
            <li class="list-group-item text-body-secondary">Nothing recorded yet.</li>
        @endforelse
    </ul>
</div>

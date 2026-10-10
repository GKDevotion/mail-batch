@extends('layouts.app')
@section('title', 'Activity log')

@php
    $badge = ['auth' => 'info', 'campaign' => 'primary', 'smtp' => 'warning', 'user' => 'secondary', 'access' => 'primary', 'system' => 'secondary'];
@endphp

@section('content')
    <div class="mb-3">
        <h1 class="h3 mb-1">Activity log</h1>
        <p class="text-body-secondary mb-0">Who did what, when and from where.</p>
    </div>

    <form method="GET" class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-md-4 col-xl-3">
            <label class="form-label small mb-1" for="q">Search</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="User, action, IP…">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small mb-1" for="module">Area</label>
            <select id="module" name="module" class="form-select">
                <option value="">All</option>
                @foreach ($modules as $m) <option value="{{ $m }}" @selected($filters['module'] === $m)>{{ ucfirst($m) }}</option> @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">From</label><input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">To</label><input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary">Filter</button>
            <a class="btn btn-link" href="{{ route('admin.activity.index') }}">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th class="text-nowrap">Time</th><th>User</th><th>Action</th><th>Description</th>
                    <th class="d-none d-lg-table-cell">IP</th><th class="d-none d-xl-table-cell">Device</th>
                </tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                        <td class="text-truncate-cell">{{ $log->user_name ?? 'System' }}</td>
                        <td><span class="badge text-bg-{{ $badge[$log->module] ?? 'light' }}">{{ $log->action }}</span></td>
                        <td class="text-break" style="max-width: 420px;">
                            {{ $log->description }}
                            @if ($log->properties)
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-bs-toggle="collapse" data-bs-target="#log{{ $log->id }}" aria-expanded="false">details</button>
                                <div class="collapse mt-2" id="log{{ $log->id }}"><pre class="small mb-0 p-2 rounded bg-body-tertiary text-wrap">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                            @endif
                        </td>
                        <td class="d-none d-lg-table-cell small text-nowrap">{{ $log->ip }}</td>
                        <td class="d-none d-xl-table-cell small text-nowrap">{{ trim(($log->browser ?? '').' · '.($log->os ?? '').' · '.($log->device ?? ''), ' ·') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">No activity recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages()) <div class="card-footer">{{ $logs->links() }}</div> @endif
    </div>
@endsection

@extends('layouts.app')
@section('title', 'Email logs (admin)')

@section('content')
    <h1 class="h3 mb-3">Administration</h1>
    @include('admin.partials.nav')

    <form method="GET" class="row g-2 mb-3">
        <div class="col-12 col-md-5 col-lg-4"><input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search email address" aria-label="Search logs"></div>
        <div class="col-7 col-md-3 col-lg-2"><select name="status" class="form-select" aria-label="Status">
            <option value="">All</option>
            @foreach (['sent', 'failed', 'test'] as $s) <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option> @endforeach
        </select></div>
        <div class="col-5 col-md-auto"><button class="btn btn-outline-secondary">Filter</button></div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="text-nowrap">Time</th><th>Campaign</th><th>Email</th><th>Status</th><th class="d-none d-md-table-cell">Detail</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                        <td class="text-truncate-cell">@if ($log->campaign) <a href="{{ route('admin.campaigns.show', $log->campaign_id) }}" class="text-decoration-none">{{ $log->campaign->name }}</a> @endif</td>
                        <td class="text-truncate-cell">{{ $log->email }}</td>
                        <td><span class="badge text-bg-{{ ['sent' => 'success', 'failed' => 'danger', 'test' => 'info'][$log->status] ?? 'secondary' }}">{{ ucfirst($log->status) }}</span></td>
                        <td class="d-none d-md-table-cell small text-break" style="max-width: 360px;">{{ $log->error_message ?? $log->response }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-5">No log entries.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages()) <div class="card-footer bg-body">{{ $logs->links() }}</div> @endif
    </div>
@endsection

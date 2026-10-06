@extends('layouts.app')
@section('title', 'Email logs')

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('campaigns.index') }}">Campaigns</a></li>
        <li class="breadcrumb-item"><a href="{{ route('campaigns.show', $campaign) }}">{{ \Illuminate\Support\Str::limit($campaign->name, 40) }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Logs</li>
    </ol></nav>
    <h1 class="h3 mb-3">Recipient logs</h1>
    @include('campaigns.partials.subnav')

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ([null => ['All', $totals->sum(), 'secondary'], 'sent' => ['Sent', $totals['sent'] ?? 0, 'success'], 'failed' => ['Failed', $totals['failed'] ?? 0, 'danger'], 'test' => ['Test', $totals['test'] ?? 0, 'info']] as $key => [$label, $n, $color])
            <a href="{{ route('campaigns.logs', array_filter(['campaign' => $campaign->id, 'status' => $key, 'q' => $search ?: null])) }}"
               class="btn btn-sm {{ ($status ?? null) === $key ? 'btn-'.$color : 'btn-outline-'.$color }}">
                {{ $label }} <span class="badge text-bg-light ms-1">{{ number_format($n) }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" class="row g-2 mb-3">
        @if ($status) <input type="hidden" name="status" value="{{ $status }}"> @endif
        <div class="col-12 col-md-6 col-lg-4">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search email address" aria-label="Search logs">
        </div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th class="text-nowrap">Time</th>
                    <th>Email</th>
                    <th class="d-none d-lg-table-cell">Subject</th>
                    <th>Status</th>
                    <th class="d-none d-md-table-cell">Detail</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                        <td class="text-truncate-cell">{{ $log->email }}</td>
                        <td class="d-none d-lg-table-cell text-truncate-cell">{{ $log->subject }}</td>
                        <td>
                            <span class="badge text-bg-{{ ['sent' => 'success', 'failed' => 'danger', 'test' => 'info'][$log->status] ?? 'secondary' }}">{{ ucfirst($log->status) }}</span>
                        </td>
                        <td class="d-none d-md-table-cell small text-break" style="max-width: 360px;">
                            @if ($log->error_message) <span class="text-danger">{{ $log->error_message }}</span>
                            @elseif ($log->response) <span class="text-body-secondary">{{ $log->response }}</span> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-5">No log entries yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer bg-body">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection

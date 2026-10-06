@extends('layouts.app')
@section('title', $campaign->name)

@section('content')
    <h1 class="h3 mb-3">Administration</h1>
    @include('admin.partials.nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h4 mb-0 text-break">{{ $campaign->name }} <x-status-badge :status="$campaign->status" /></h2>
        @if ($campaign->status === \App\Enums\CampaignStatus::Processing)
            <form method="POST" action="{{ route('admin.campaigns.pause', $campaign) }}">@csrf<button class="btn btn-warning"><i class="bi bi-pause-fill me-1"></i>Pause campaign</button></form>
        @endif
    </div>

    @if ($snapshot['last_error']) <div class="alert alert-warning">{{ $snapshot['last_error'] }}</div> @endif

    <div class="progress mb-3" style="height: 18px;" role="progressbar" aria-valuenow="{{ $snapshot['percent'] }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar" style="width: {{ $snapshot['percent'] }}%">{{ $snapshot['percent'] }}%</div>
    </div>

    <div class="row g-3 mb-3">
        @foreach (['total' => 'Total', 'eligible' => 'Eligible', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped', 'remaining' => 'Remaining'] as $k => $label)
            <div class="col-4 col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
                <div class="small text-body-secondary">{{ $label }}</div><div class="fw-semibold">{{ number_format($snapshot[$k]) }}</div>
            </div></div></div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5"><div class="card border-0 shadow-sm"><div class="card-header bg-body fw-semibold">Details</div>
            <dl class="row mb-0 p-3 small">
                <dt class="col-5">Owner</dt><dd class="col-7 text-break">{{ $campaign->user?->name }} ({{ $campaign->user?->email }})</dd>
                <dt class="col-5">Excel file</dt><dd class="col-7 text-break">{{ $campaign->excel_filename ?? '—' }}</dd>
                <dt class="col-5">SMTP</dt><dd class="col-7 text-break">{{ $campaign->smtpAccount ? $campaign->smtpAccount->name.' — '.$campaign->smtpAccount->smtp_host.':'.$campaign->smtpAccount->smtp_port : '—' }}</dd>
                <dt class="col-5">Subject</dt><dd class="col-7 text-break">{{ $campaign->subject ?? '—' }}</dd>
                <dt class="col-5">Batch size</dt><dd class="col-7">{{ $campaign->batch_size }}</dd>
                <dt class="col-5">Started</dt><dd class="col-7">{{ $campaign->started_at?->format('d M Y H:i') ?? '—' }}</dd>
                <dt class="col-5">Completed</dt><dd class="col-7">{{ $campaign->completed_at?->format('d M Y H:i') ?? '—' }}</dd>
            </dl></div></div>
        <div class="col-12 col-lg-7"><div class="card border-0 shadow-sm"><div class="card-header bg-body fw-semibold">Latest log entries</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at->format('d M H:i:s') }}</td>
                        <td class="text-truncate-cell">{{ $log->email }}</td>
                        <td><span class="badge text-bg-{{ ['sent' => 'success', 'failed' => 'danger', 'test' => 'info'][$log->status] ?? 'secondary' }}">{{ ucfirst($log->status) }}</span></td>
                        <td class="small text-danger text-break d-none d-md-table-cell">{{ \Illuminate\Support\Str::limit($log->error_message, 80) }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-4">No entries.</td></tr>
                @endforelse
            </tbody></table></div></div></div>
    </div>
@endsection

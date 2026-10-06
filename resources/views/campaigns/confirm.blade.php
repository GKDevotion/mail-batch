@extends('layouts.app')
@section('title', 'Confirm & send')

@php
    $already = (int) ($reasons['already_marked_sent'] ?? 0);
    $invalid = (int) ($reasons['invalid_email'] ?? 0) + (int) ($reasons['missing_email'] ?? 0);
    $checks = [
        ['Excel file imported and columns mapped', filled($campaign->excel_path) && ! empty($campaign->column_mapping['email'] ?? null), route('campaigns.mapping', $campaign)],
        ['SMTP account selected', (bool) $campaign->smtp_account_id, route('campaigns.smtp', $campaign)],
        ['Email subject and body written', filled($campaign->subject) && filled($campaign->body_html), route('campaigns.compose', $campaign)],
    ];
@endphp

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('campaigns.index') }}">Campaigns</a></li>
        <li class="breadcrumb-item"><a href="{{ route('campaigns.show', $campaign) }}">{{ \Illuminate\Support\Str::limit($campaign->name, 40) }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Confirm &amp; send</li>
    </ol></nav>
    <h1 class="h3 mb-3">Confirm &amp; send</h1>
    @include('campaigns.partials.subnav')

    <div id="sendPanel" class="row g-3"
         data-status-url="{{ route('campaigns.progress.status', $campaign) }}"
         data-work-url="{{ route('campaigns.work', $campaign) }}"
         data-start-url="{{ route('campaigns.start', $campaign) }}"
         data-pause-url="{{ route('campaigns.pause', $campaign) }}"
         data-retry-url="{{ route('campaigns.retry-failed', $campaign) }}"
         data-batch="{{ min($campaign->batch_size, \App\Services\CampaignService::maxBatchSize()) }}">

        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-body fw-semibold">Campaign summary</div>
                <dl class="row mb-0 p-3 small">
                    <dt class="col-6">Excel file</dt><dd class="col-6 text-break">{{ $campaign->excel_filename }}</dd>
                    <dt class="col-6">Total rows</dt><dd class="col-6">{{ number_format($campaign->total_records) }}</dd>
                    <dt class="col-6">Valid emails</dt><dd class="col-6">{{ number_format($campaign->valid_records) }}</dd>
                    <dt class="col-6">Invalid emails</dt><dd class="col-6">{{ number_format($invalid) }}</dd>
                    <dt class="col-6">Already sent (Excel status 1)</dt><dd class="col-6">{{ number_format($already) }}</dd>
                    <dt class="col-6">Eligible recipients</dt><dd class="col-6">{{ number_format($campaign->eligible_records) }}</dd>
                    <dt class="col-6">Batch size</dt><dd class="col-6">{{ $campaign->batch_size }} per click</dd>
                    <dt class="col-6">Sending today</dt><dd class="col-6">{{ number_format($sentToday) }} / {{ number_format($dailyLimit) }}</dd>
                    <dt class="col-6">SMTP account</dt><dd class="col-6 text-break">{{ $campaign->smtpAccount?->name ?? '—' }}</dd>
                    <dt class="col-6">Subject</dt><dd class="col-6 text-break">{{ $campaign->subject ?? '—' }}</dd>
                    <dt class="col-6">Unsubscribe link</dt><dd class="col-6">{{ $campaign->include_unsubscribe ? 'Included' : 'Not included' }}</dd>
                </dl>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-body fw-semibold">Readiness</div>
                <ul class="list-group list-group-flush">
                    @foreach ($checks as [$label, $ok, $link])
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <span><i class="bi {{ $ok ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle-fill text-warning' }} me-2"></i>{{ $label }}</span>
                            @unless ($ok) <a href="{{ $link }}" class="btn btn-sm btn-primary">Fix</a> @endunless
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            @include('campaigns.partials.send-panel')
        </div>
    </div>
@endsection

@push('scripts') <script src="{{ asset('assets/js/send.js') }}"></script> @endpush

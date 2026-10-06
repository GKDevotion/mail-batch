@extends('layouts.app')
@section('title', $campaign->name)

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div class="min-w-0">
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('campaigns.index') }}">Campaigns</a></li>
                <li class="breadcrumb-item active" aria-current="page">Details</li>
            </ol></nav>
            <h1 class="h3 mb-0 text-break">{{ $campaign->name }} <x-status-badge :status="$campaign->status" /></h1>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($campaign->total_records)
                <a href="{{ route('campaigns.progress', $campaign) }}" class="btn btn-outline-primary"><i class="bi bi-activity me-1"></i>Progress</a>
                <a href="{{ route('campaigns.recipients', $campaign) }}" class="btn btn-outline-primary"><i class="bi bi-people me-1"></i>Recipients</a>
                <a href="{{ route('campaigns.logs', $campaign) }}" class="btn btn-outline-primary"><i class="bi bi-journal-text me-1"></i>Logs</a>
                <a href="{{ route('campaigns.export', $campaign) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Export</a>
            @endif
            @can('configure', $campaign)
                <a href="{{ route('campaigns.edit', $campaign) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
            @endcan
            @can('delete', $campaign)
                <button type="button" class="btn btn-outline-danger" data-confirm-delete
                        data-action="{{ route('campaigns.destroy', $campaign) }}"
                        data-message="Delete this campaign and all its recipients and logs? This cannot be undone.">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Total', $campaign->total_records, 'secondary'],
            ['Eligible', $campaign->eligible_records, 'info'],
            ['Sent', $campaign->sent_count, 'success'],
            ['Failed', $campaign->failed_count, 'danger'],
            ['Skipped', $campaign->skipped_count, 'warning'],
            ['Remaining', $campaign->remaining_count, 'primary'],
        ] as [$label, $value, $color])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                    <div class="small text-body-secondary">{{ $label }}</div>
                    <div class="fs-4 fw-semibold text-{{ $color }}-emphasis">{{ number_format($value) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-body fw-semibold">Campaign steps</div>
                @include('campaigns.partials.wizard-steps')
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-body fw-semibold">Details</div>
                <dl class="row mb-0 p-3 small">
                    <dt class="col-5">Excel file</dt><dd class="col-7 text-break">{{ $campaign->excel_filename ?? '—' }}</dd>
                    <dt class="col-5">SMTP account</dt><dd class="col-7 text-break">{{ $campaign->smtpAccount?->name ?? '—' }}</dd>
                    <dt class="col-5">Batch size</dt><dd class="col-7">{{ $campaign->batch_size }}</dd>
                    @if ($campaign->total_records)
                        <dt class="col-5">Valid / invalid</dt><dd class="col-7">{{ number_format($campaign->valid_records) }} / {{ number_format($campaign->invalid_records) }}</dd>
                        <dt class="col-5">Duplicates</dt><dd class="col-7">{{ number_format($campaign->duplicate_records) }}</dd>
                    @endif
                    <dt class="col-5">Unsubscribe link</dt><dd class="col-7">{{ $campaign->include_unsubscribe ? 'Included' : 'Not included' }}</dd>
                    <dt class="col-5">Created</dt><dd class="col-7">{{ $campaign->created_at->format('d M Y, H:i') }}</dd>
                    <dt class="col-5">Started</dt><dd class="col-7">{{ $campaign->started_at?->format('d M Y, H:i') ?? '—' }}</dd>
                    <dt class="col-5">Completed</dt><dd class="col-7">{{ $campaign->completed_at?->format('d M Y, H:i') ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
@endsection

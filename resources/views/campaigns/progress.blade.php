@extends('layouts.app')
@section('title', 'Sending progress')

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('campaigns.index') }}">Campaigns</a></li>
        <li class="breadcrumb-item"><a href="{{ route('campaigns.show', $campaign) }}">{{ \Illuminate\Support\Str::limit($campaign->name, 40) }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Progress</li>
    </ol></nav>
    <h1 class="h3 mb-3">Sending progress</h1>
    @include('campaigns.partials.subnav')

    <div id="sendPanel" class="row g-3"
         data-status-url="{{ route('campaigns.progress.status', $campaign) }}"
         data-work-url="{{ route('campaigns.work', $campaign) }}"
         data-start-url="{{ route('campaigns.start', $campaign) }}"
         data-pause-url="{{ route('campaigns.pause', $campaign) }}"
         data-retry-url="{{ route('campaigns.retry-failed', $campaign) }}"
         data-batch="{{ min($campaign->batch_size, \App\Services\CampaignService::maxBatchSize()) }}">
        <div class="col-12 col-lg-8">
            @include('campaigns.partials.send-panel')
        </div>
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-body d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Recent activity</span>
                    <a href="{{ route('campaigns.logs', $campaign) }}" class="small">All logs</a>
                </div>
                <ul class="list-group list-group-flush" id="activityFeed" aria-live="polite">
                    @forelse ($snapshot['recent'] as $r)
                        <li class="list-group-item d-flex justify-content-between gap-2" title="{{ $r['error'] }}">
                            <span class="text-truncate">{{ $r['email'] }}</span>
                            <span class="badge {{ $r['status'] === 'sent' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $r['status'] }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">No emails yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('scripts') <script src="{{ asset('assets/js/send.js') }}"></script> @endpush

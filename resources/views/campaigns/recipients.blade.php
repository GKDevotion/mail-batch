@extends('layouts.app')
@section('title', 'Recipients')

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('campaigns.index') }}">Campaigns</a></li>
        <li class="breadcrumb-item"><a href="{{ route('campaigns.show', $campaign) }}">{{ \Illuminate\Support\Str::limit($campaign->name, 40) }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Recipients</li>
    </ol></nav>
    <h1 class="h3 mb-3">Recipients</h1>
    @include('campaigns.partials.subnav')

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ([null => ['All', $counts['total'], 'secondary'], 'pending' => ['Pending', $counts['pending'], 'secondary'], 'sent' => ['Sent', $counts['sent'], 'success'], 'failed' => ['Failed', $counts['failed'], 'danger'], 'skipped' => ['Skipped', $counts['skipped'], 'warning']] as $key => [$label, $n, $color])
            <a href="{{ route('campaigns.recipients', array_filter(['campaign' => $campaign->id, 'state' => $key, 'q' => $search ?: null])) }}"
               class="btn btn-sm {{ ($state ?? null) === $key ? 'btn-'.$color : 'btn-outline-'.$color }}">
                {{ $label }} <span class="badge text-bg-light ms-1">{{ number_format($n) }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" class="row g-2 mb-3">
        @if ($state) <input type="hidden" name="state" value="{{ $state }}"> @endif
        <div class="col-12 col-md-6 col-lg-4">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search name, email or website" aria-label="Search recipients">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary">Search</button>
            @if ($search !== '') <a class="btn btn-link" href="{{ route('campaigns.recipients', array_filter(['campaign' => $campaign->id, 'state' => $state])) }}">Reset</a> @endif
        </div>
    </form>

    <div id="recipientFlash" role="status" aria-live="polite"></div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="recipientsTable" data-state-url="{{ route('campaigns.recipients.state', $campaign) }}" data-work-url="{{ route('campaigns.work', $campaign) }}">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th class="d-none d-lg-table-cell">Website</th>
                    <th>Status</th>
                    <th class="d-none d-md-table-cell">Sent at</th>
                    <th class="d-none d-xl-table-cell">Error</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($recipients as $recipient)
                    @include('campaigns.partials.recipient-row')
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-5">No recipients match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($recipients->hasPages())
            <div class="card-footer bg-body">{{ $recipients->links() }}</div>
        @endif
    </div>
@endsection

@push('scripts') <script src="{{ asset('assets/js/recipients.js') }}"></script> @endpush

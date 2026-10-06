@extends('layouts.app')
@section('title', 'Campaigns (admin)')

@section('content')
    <h1 class="h3 mb-3">Administration</h1>
    @include('admin.partials.nav')

    <form method="GET" class="row g-2 mb-3">
        <div class="col-12 col-md-5 col-lg-4"><input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search campaign name" aria-label="Search campaigns"></div>
        <div class="col-7 col-md-3 col-lg-2"><select name="status" class="form-select" aria-label="Status">
            <option value="">All statuses</option>
            @foreach ($statuses as $s) <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option> @endforeach
        </select></div>
        <div class="col-5 col-md-auto"><button class="btn btn-outline-secondary">Filter</button></div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>Campaign</th><th>Owner</th><th>Status</th>
                    <th class="d-none d-md-table-cell text-end">Sent / failed / skipped</th>
                    <th class="d-none d-lg-table-cell">Created</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                @forelse ($campaigns as $c)
                    <tr>
                        <td class="text-truncate-cell"><a class="fw-semibold text-decoration-none" href="{{ route('admin.campaigns.show', $c) }}">{{ $c->name }}</a></td>
                        <td class="text-truncate-cell">{{ $c->user?->name }}</td>
                        <td><x-status-badge :status="$c->status" /></td>
                        <td class="d-none d-md-table-cell text-end text-nowrap">{{ number_format($c->sent_count) }} / {{ number_format($c->failed_count) }} / {{ number_format($c->skipped_count) }} <span class="text-body-secondary">of {{ number_format($c->total_records) }}</span></td>
                        <td class="d-none d-lg-table-cell text-nowrap small">{{ $c->created_at->format('d M Y') }}</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.campaigns.show', $c) }}">View</a>
                            @if ($c->status === \App\Enums\CampaignStatus::Processing)
                                <form method="POST" action="{{ route('admin.campaigns.pause', $c) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-warning">Pause</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">No campaigns.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($campaigns->hasPages()) <div class="card-footer bg-body">{{ $campaigns->links() }}</div> @endif
    </div>
@endsection

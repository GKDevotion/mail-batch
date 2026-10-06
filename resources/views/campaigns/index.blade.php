@extends('layouts.app')
@section('title', 'Campaigns')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Campaigns</h1>
        <a class="btn btn-primary" href="{{ route('campaigns.create') }}"><i class="bi bi-plus-lg me-1"></i>New campaign</a>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-12 col-md-6 col-lg-4">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search by name" aria-label="Search campaigns">
        </div>
        <div class="col-7 col-md-3 col-lg-2">
            <select name="status" class="form-select" aria-label="Filter by status">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-5 col-md-auto">
            <button class="btn btn-outline-secondary">Filter</button>
            @if ($search !== '' || $status) <a href="{{ route('campaigns.index') }}" class="btn btn-link">Reset</a> @endif
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        @include('campaigns.partials.table', ['campaigns' => $campaigns])
        @if ($campaigns->hasPages())
            <div class="card-footer bg-body">{{ $campaigns->links() }}</div>
        @endif
    </div>
@endsection

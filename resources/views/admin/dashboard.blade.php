@extends('layouts.app')
@section('title', 'Admin')

@section('content')
    <h1 class="h3 mb-3">Administration</h1>
    @include('admin.partials.nav')

    <div class="row g-3 mb-4">
        @foreach ($stats as $label => $value)
            <div class="col-6 col-md-4 col-xl">
                <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                    <div class="small text-body-secondary text-truncate">{{ $label }}</div>
                    <div class="fs-4 fw-semibold">{{ $value === null ? '—' : number_format($value) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-body d-flex justify-content-between"><span class="fw-semibold">Recent failures</span><a class="small" href="{{ route('admin.logs.failed') }}">All failed emails</a></div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <tbody>
                    @forelse ($recentFailures as $log)
                        <tr>
                            <td class="text-nowrap small">{{ $log->created_at->format('d M H:i') }}</td>
                            <td class="text-truncate-cell">{{ $log->email }}</td>
                            <td class="small text-danger text-break">{{ \Illuminate\Support\Str::limit($log->error_message, 90) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-4">No failures.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-body fw-semibold">Top senders today</div>
                <ul class="list-group list-group-flush">
                    @forelse ($topSenders as $row)
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span class="text-truncate">{{ $row->user?->name }} <span class="text-body-secondary small">{{ $row->user?->email }}</span></span>
                            <span class="fw-semibold">{{ number_format($row->sent) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">Nothing sent today.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

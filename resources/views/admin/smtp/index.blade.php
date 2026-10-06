@extends('layouts.app')
@section('title', 'SMTP accounts (admin)')

@section('content')
    <h1 class="h3 mb-3">Administration</h1>
    @include('admin.partials.nav')

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>Account</th><th>Owner</th><th>Server</th><th class="d-none d-lg-table-cell">From</th><th>Last test</th><th>Status</th><th class="text-end">Action</th>
                </tr></thead>
                <tbody>
                @forelse ($accounts as $a)
                    <tr>
                        <td class="text-truncate-cell fw-semibold">{{ $a->name }}<div class="small fw-normal text-body-secondary">{{ $a->campaigns_count }} campaign(s)</div></td>
                        <td class="text-truncate-cell">{{ $a->user?->name }}<div class="small text-body-secondary text-truncate">{{ $a->user?->email }}</div></td>
                        <td class="text-nowrap">{{ $a->smtp_host }}:{{ $a->smtp_port }} <span class="badge text-bg-light border">{{ $a->encryption->label() }}</span></td>
                        <td class="d-none d-lg-table-cell text-truncate-cell">{{ $a->from_email }}</td>
                        <td>@if ($a->last_test_ok === null) <span class="badge text-bg-secondary">Not tested</span> @elseif ($a->last_test_ok) <span class="badge text-bg-success">Passed</span> @else <span class="badge text-bg-danger">Failed</span> @endif</td>
                        <td><span class="badge text-bg-{{ $a->isActive() ? 'success' : 'danger' }}">{{ $a->isActive() ? 'Active' : 'Disabled' }}</span></td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.smtp.toggle', $a) }}">@csrf
                                <button class="btn btn-sm btn-outline-{{ $a->isActive() ? 'warning' : 'success' }}">{{ $a->isActive() ? 'Disable' : 'Enable' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-5">No SMTP accounts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($accounts->hasPages()) <div class="card-footer bg-body">{{ $accounts->links() }}</div> @endif
    </div>
    <p class="form-text mt-2">SMTP passwords are encrypted and are never shown, not even to administrators.</p>
@endsection

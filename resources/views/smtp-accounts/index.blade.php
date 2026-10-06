@extends('layouts.app')
@section('title', 'SMTP accounts')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0">SMTP accounts</h1>
            <p class="text-body-secondary mb-0">Reusable sending accounts. Passwords are encrypted and never displayed.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('smtp-accounts.create') }}"><i class="bi bi-plus-lg me-1"></i>Add account</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Server</th>
                    <th class="d-none d-lg-table-cell">From</th>
                    <th>Last test</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td class="text-truncate-cell fw-semibold">{{ $account->name }}
                            <div class="small fw-normal text-body-secondary text-truncate">{{ $account->smtp_username }}</div></td>
                        <td class="text-nowrap">{{ $account->smtp_host }}:{{ $account->smtp_port }}
                            <span class="badge text-bg-light border">{{ $account->encryption->label() }}</span></td>
                        <td class="d-none d-lg-table-cell text-truncate-cell">{{ $account->from_name }} &lt;{{ $account->from_email }}&gt;</td>
                        <td>
                            @if ($account->last_test_ok === null) <span class="badge text-bg-secondary">Not tested</span>
                            @elseif ($account->last_test_ok) <span class="badge text-bg-success">Passed</span>
                            @else <span class="badge text-bg-danger">Failed</span> @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('smtp-accounts.edit', $account) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @can('delete', $account)
                                <button type="button" class="btn btn-sm btn-outline-danger" data-confirm-delete
                                        data-action="{{ route('smtp-accounts.destroy', $account) }}"
                                        data-message="Delete SMTP account &quot;{{ $account->name }}&quot;? {{ $account->campaigns_count }} campaign(s) use it and will need another account.">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-5">No SMTP accounts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

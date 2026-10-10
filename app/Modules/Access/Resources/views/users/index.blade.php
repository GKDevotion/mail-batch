@extends('layouts.app')
@section('title', 'Assign roles')

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('admin.access.roles') }}">Roles &amp; access</a></li>
        <li class="breadcrumb-item active" aria-current="page">Assign roles</li>
    </ol></nav>
    <h1 class="h3 mb-3">Assign roles</h1>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-12 col-md-6 col-lg-4"><input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search name or email" aria-label="Search users"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>User</th><th class="d-none d-md-table-cell">Current role</th><th class="text-end">Change</th></tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td class="text-truncate-cell fw-semibold">{{ $u->name }}<div class="small fw-normal text-body-secondary text-truncate">{{ $u->email }}</div></td>
                        @if ($u->isAdmin())
                            <td colspan="2"><span class="badge text-bg-primary">Super Admin</span> <span class="small text-body-secondary">all permissions</span></td>
                        @else
                            <td class="d-none d-md-table-cell">{{ $u->accessRole?->name ?? ($defaultName.' (default)') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.access.users.update', $u) }}" class="d-inline-flex gap-2 justify-content-end">
                                    @csrf @method('PUT')
                                    <select name="role_id" class="form-select form-select-sm w-auto" aria-label="Role for {{ $u->email }}">
                                        <option value="">Default ({{ $defaultName }})</option>
                                        @foreach ($roles as $r) <option value="{{ $r->id }}" @selected($u->role_id === $r->id)>{{ $r->name }}</option> @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-primary">Save</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($users->hasPages()) <div class="card-footer">{{ $users->links() }}</div> @endif
    </div>
@endsection

@extends('layouts.app')
@section('title', 'Users')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Users</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal"><i class="bi bi-person-plus me-1"></i>New user</button>
    </div>
    @include('admin.partials.nav')

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul></div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-12 col-md-6 col-lg-4"><input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search name or email" aria-label="Search users"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>User</th><th>Role</th><th>Status</th>
                    <th class="d-none d-md-table-cell text-end">Today / limit</th>
                    <th class="d-none d-lg-table-cell text-end">Campaigns</th>
                    <th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td class="text-truncate-cell fw-semibold">{{ $u->name }}<div class="small fw-normal text-body-secondary text-truncate">{{ $u->email }}</div></td>
                        <td><span class="badge text-bg-{{ $u->isAdmin() ? 'primary' : 'secondary' }}">{{ ucfirst($u->role->value) }}</span></td>
                        <td><span class="badge text-bg-{{ $u->is_active ? 'success' : 'danger' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
                        <td class="d-none d-md-table-cell text-end text-nowrap">
                            {{ number_format($sentToday[$u->id] ?? 0) }} / {{ number_format($u->effectiveDailyLimit()) }}
                            @if ($u->daily_send_limit === null) <span class="text-body-secondary small">(default)</span> @endif
                        </td>
                        <td class="d-none d-lg-table-cell text-end">{{ $u->campaigns_count }}</td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-edit-user
                                    data-action="{{ route('admin.users.update', $u) }}" data-name="{{ $u->name }}"
                                    data-role="{{ $u->role->value }}" data-active="{{ $u->is_active ? 1 : 0 }}"
                                    data-limit="{{ $u->daily_send_limit }}">Edit</button>
                            @unless ($u->is(auth()->user()))
                                <button type="button" class="btn btn-sm btn-outline-danger" data-confirm-delete
                                        data-action="{{ route('admin.users.destroy', $u) }}"
                                        data-message="Delete {{ $u->email }} together with their {{ $u->campaigns_count }} campaign(s), recipients and logs? This cannot be undone."><i class="bi bi-trash"></i></button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($users->hasPages()) <div class="card-footer bg-body">{{ $users->links() }}</div> @endif
    </div>

    {{-- Create --}}
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.users.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="createUserTitle">New user</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="cu_name">Name</label><input id="cu_name" name="name" class="form-control" required maxlength="120" value="{{ old('name') }}"></div>
                <div class="mb-3"><label class="form-label" for="cu_email">Email</label><input id="cu_email" type="email" name="email" class="form-control" required value="{{ old('email') }}"></div>
                <div class="mb-3"><label class="form-label" for="cu_pw">Initial password</label><input id="cu_pw" type="password" name="password" class="form-control" required autocomplete="new-password">
                    <div class="form-text">Minimum 10 characters, upper and lower case and a number. The user can change it under Account.</div></div>
                <div class="row g-3">
                    <div class="col-6"><label class="form-label" for="cu_role">Role</label><select id="cu_role" name="role" class="form-select"><option value="user">User</option><option value="admin">Admin</option></select></div>
                    <div class="col-6"><label class="form-label" for="cu_limit">Daily limit</label><input id="cu_limit" type="number" min="0" name="daily_send_limit" class="form-control" placeholder="default"></div>
                </div>
                <div class="form-check form-switch mt-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" role="switch" id="cu_active" name="is_active" value="1" checked><label class="form-check-label" for="cu_active">Active</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create user</button></div>
        </form></div>
    </div>

    {{-- Edit --}}
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title" id="editUserTitle">Edit <span id="editUserName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label" for="eu_role">Role</label><select id="eu_role" name="role" class="form-select"><option value="user">User</option><option value="admin">Admin</option></select></div>
                    <div class="col-6"><label class="form-label" for="eu_limit">Daily limit</label><input id="eu_limit" type="number" min="0" name="daily_send_limit" class="form-control" placeholder="default"></div>
                </div>
                <div class="form-check form-switch mt-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" role="switch" id="eu_active" name="is_active" value="1"><label class="form-check-label" for="eu_active">Active (disabling also pauses running campaigns)</label></div>
                <div class="mt-3"><label class="form-label" for="eu_pw">Reset password <span class="text-body-secondary">(optional)</span></label><input id="eu_pw" type="password" name="password" class="form-control" autocomplete="new-password"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
        </form></div>
    </div>
@endsection

@push('scripts') <script src="{{ asset('assets/js/admin.js') }}"></script> @endpush

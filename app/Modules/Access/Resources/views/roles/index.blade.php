@extends('layouts.app')
@section('title', 'Roles & access')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Roles &amp; access</h1>
            <p class="text-body-secondary mb-0">Roles decide what people can do inside modules.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('admin.access.users') }}"><i class="bi bi-people me-1"></i>Assign roles</a>
            <a class="btn btn-primary" href="{{ route('admin.access.roles.create') }}"><i class="bi bi-plus-lg me-1"></i>New role</a>
        </div>
    </div>

    <div class="alert alert-info small">
        <strong>Super Admin</strong> is the Admin flag on <em>Admin &gt; Users</em>: it always has every permission and is not a role here.
        Users without a role get <strong>{{ $roles->firstWhere('key', $defaultKey)?->name ?? 'Campaign Manager' }}</strong>.
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Role</th><th class="d-none d-md-table-cell">Description</th><th class="text-center">Users</th><th class="text-center">Permissions</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td class="fw-semibold text-nowrap">{{ $role->name }}
                            @if ($role->is_system) <span class="badge text-bg-secondary ms-1">Built-in</span> @endif
                            @if ($role->key === $defaultKey) <span class="badge text-bg-primary ms-1">Default</span> @endif
                        </td>
                        <td class="d-none d-md-table-cell small text-body-secondary">{{ $role->description }}</td>
                        <td class="text-center">{{ $role->users_count }}</td>
                        <td class="text-center">{{ $role->permissions_count }}</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.access.roles.edit', $role) }}">Edit</a>
                            @unless ($role->is_system)
                                <button type="button" class="btn btn-sm btn-outline-danger" data-confirm-delete
                                        data-action="{{ route('admin.access.roles.destroy', $role) }}"
                                        data-message="Delete the role “{{ $role->name }}”?"><i class="bi bi-trash"></i></button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

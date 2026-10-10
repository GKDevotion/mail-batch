@extends('layouts.app')
@section('title', $role->exists ? 'Edit role' : 'New role')

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('admin.access.roles') }}">Roles &amp; access</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $role->exists ? $role->name : 'New role' }}</li>
    </ol></nav>
    <h1 class="h3 mb-3">{{ $role->exists ? 'Edit role' : 'New role' }}</h1>

    <form method="POST" action="{{ $role->exists ? route('admin.access.roles.update', $role) : route('admin.access.roles.store') }}" novalidate>
        @csrf
        @if ($role->exists) @method('PUT') @endif

        <div class="card mb-3"><div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-5">
                    <label for="name" class="form-label">Name</label>
                    <input id="name" name="name" maxlength="80" required value="{{ old('name', $role->name) }}" @readonly($role->is_system)
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-7">
                    <label for="description" class="form-label">Description</label>
                    <input id="description" name="description" maxlength="255" value="{{ old('description', $role->description) }}"
                           class="form-control @error('description') is-invalid @enderror">
                </div>
            </div>
        </div></div>

        @error('permissions.*') <div class="alert alert-danger">{{ $message }}</div> @enderror

        <div class="row g-3 mb-3">
            @forelse ($groups as $moduleKey => $group)
                <div class="col-12 col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>{{ $group['name'] }}</span>
                            <label class="form-check mb-0 small fw-normal"><input type="checkbox" class="form-check-input me-1" data-check-all="{{ $moduleKey }}">All</label>
                        </div>
                        <div class="card-body">
                            @foreach ($group['permissions'] as $permission => $label)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" id="p_{{ $loop->parent->index }}_{{ $loop->index }}"
                                           data-check-group="{{ $moduleKey }}" @checked(in_array($permission, old('permissions', $granted), true))>
                                    <label class="form-check-label" for="p_{{ $loop->parent->index }}_{{ $loop->index }}">{{ $label }}
                                        <code class="small ms-1">{{ $permission }}</code></label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-secondary">No module has registered permissions yet.</div></div>
            @endforelse
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary">Save role</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.access.roles') }}">Cancel</a>
        </div>
    </form>
@endsection

@push('scripts') <script src="{{ asset('assets/js/modules.js') }}"></script> @endpush

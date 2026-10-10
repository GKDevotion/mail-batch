@extends('layouts.app')
@section('title', 'Modules')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Modules</h1>
            <p class="text-body-secondary mb-0">Each feature is a module. Add one with <code>php artisan make:module</code>.</p>
        </div>
        <form method="POST" action="{{ route('admin.modules.migrate') }}">
            @csrf
            <button class="btn {{ $pendingTotal ? 'btn-primary' : 'btn-outline-secondary' }}" data-confirm-run>
                <i class="bi bi-arrow-repeat me-1"></i>Run updates @if ($pendingTotal) <span class="badge text-bg-light ms-1">{{ $pendingTotal }}</span> @endif
            </button>
        </form>
    </div>

    @if (session('migrate_output'))
        <div class="card mb-3"><div class="card-header">Update output</div><div class="card-body"><pre class="small mb-0 text-wrap">{{ session('migrate_output') }}</pre></div></div>
    @endif

    @if ($pendingTotal)
        <div class="alert alert-warning small">There are <strong>{{ $pendingTotal }}</strong> pending database update(s). Back up the database, then press <strong>Run updates</strong>.</div>
    @endif

    <div class="row g-3">
        @foreach ($rows as $row)
            @php($m = $row['module'])
            @php($st = $row['status']['status'])
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="d-flex gap-3 min-w-0">
                                <span class="mb-stat-icon flex-shrink-0" style="--c: #5b47f5"><i class="bi bi-{{ $m->icon() }}"></i></span>
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ $m->name() }}
                                        <span class="text-body-secondary small fw-normal">v{{ $m->version() }}</span></div>
                                    <div class="small text-body-secondary">{{ $m->description() }}</div>
                                </div>
                            </div>
                            <span class="badge text-bg-{{ ['active' => 'success', 'disabled' => 'secondary', 'blocked' => 'warning'][$st] ?? 'light' }}">{{ ucfirst($st) }}</span>
                        </div>

                        <div class="small mt-3 d-flex flex-wrap gap-2">
                            <span class="badge text-bg-light"><code>{{ $m->key() }}</code></span>
                            @if ($m->core()) <span class="badge text-bg-primary">Core</span> @endif
                            @if (count($m->permissions())) <span class="badge text-bg-light">{{ count($m->permissions()) }} permission(s)</span> @endif
                            @foreach ($m->dependsOn() as $dep) <span class="badge text-bg-light">needs {{ $dep }}</span> @endforeach
                            @if (count($row['pending'])) <span class="badge text-bg-warning">{{ count($row['pending']) }} pending update(s)</span> @endif
                        </div>

                        @if ($row['status']['reason']) <div class="small text-warning-emphasis mt-2">{{ $row['status']['reason'] }}</div> @endif
                    </div>
                    <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
                        @if (count($m->settings()))
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.modules.settings', $m->key()) }}"><i class="bi bi-sliders me-1"></i>Settings</a>
                        @endif
                        @unless ($m->core())
                            <form method="POST" action="{{ route('admin.modules.toggle', $m->key()) }}">
                                @csrf
                                <input type="hidden" name="enabled" value="{{ $st === 'disabled' ? 1 : 0 }}">
                                <button class="btn btn-sm {{ $st === 'disabled' ? 'btn-success' : 'btn-outline-secondary' }}">{{ $st === 'disabled' ? 'Enable' : 'Disable' }}</button>
                            </form>
                        @endunless
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

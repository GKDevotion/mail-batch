@php
    $authUser = auth()->user();
    $parts = preg_split('/\s+/', trim($authUser->name)) ?: [''];
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    $hue = crc32(strtolower($authUser->email)) % 360;
    $adminOpen = request()->routeIs('admin.*');
    try {
        $workerActive = \App\Models\Campaign::query()->where('user_id', $authUser->id)->where('status', 'processing')->exists();
    } catch (\Throwable $e) {
        $workerActive = false;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#5b47f5">
    <title>@yield('title', 'Dashboard') · {{ config('mailbatch.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        // apply saved theme / sidebar state before first paint (no flash)
        try {
            document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('mb.theme') || 'light');
            if (localStorage.getItem('mb.sb') === '1') { document.documentElement.classList.add('sb-collapsed'); }
        } catch (e) {}
    </script>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    @foreach ([400, 500, 600, 700] as $w)
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/{{ $w }}.css">
    @endforeach
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ @filemtime(public_path('assets/css/app.css')) }}">
    @stack('styles')
</head>
<body>
<div id="mbLoader" class="mb-loader" aria-hidden="true"></div>
<a class="visually-hidden-focusable position-absolute p-2 bg-white" href="#main">Skip to content</a>

<div class="mb-shell">
    {{-- ============ Sidebar (off-canvas below 992px) ============ --}}
    <aside class="mb-sidebar">
        <div class="offcanvas-lg offcanvas-start mb-offcanvas" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
            <div class="mb-sidebar-inner">
                <div class="mb-brand">
                    <a href="{{ route('dashboard') }}" class="mb-brand-link" title="{{ config('mailbatch.name') }}">
                        <img src="{{ asset('assets/img/logo-mark.svg') }}" width="38" height="38" alt="MailBatch logo">
                        <span class="mb-brand-text mb-label" id="sidebarLabel">MailBatch</span>
                    </a>
                    <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
                </div>

                <div class="mb-scroll">
                    <form method="GET" action="{{ route('campaigns.index') }}" class="px-3 pb-2 d-md-none" role="search">
                        <div class="mb-search">
                            <i class="bi bi-search"></i>
                            <input type="search" name="q" class="form-control" placeholder="Search campaigns…" aria-label="Search campaigns">
                        </div>
                    </form>

                    <nav class="mb-nav" aria-label="Main menu">
                        <a class="mb-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard">
                            <i class="bi bi-grid"></i><span class="mb-label">Dashboard</span>
                        </a>

                        <div class="mb-section-title">Sending</div>
                        <a class="mb-link {{ request()->routeIs('campaigns.*') ? 'active' : '' }}" href="{{ route('campaigns.index') }}" title="Campaigns">
                            <i class="bi bi-megaphone"></i><span class="mb-label">Campaigns</span>
                        </a>
                        <a class="mb-link {{ request()->routeIs('smtp-accounts.*') ? 'active' : '' }}" href="{{ route('smtp-accounts.index') }}" title="SMTP accounts">
                            <i class="bi bi-hdd-network"></i><span class="mb-label">SMTP accounts</span>
                        </a>

                        <div class="mb-section-title">Settings</div>
                        <a class="mb-link {{ request()->routeIs('account.*') ? 'active' : '' }}" href="{{ route('account.edit') }}" title="Account">
                            <i class="bi bi-person-gear"></i><span class="mb-label">Account</span>
                        </a>

                        @if ($authUser->isAdmin())
                            <div class="mb-section-title">Administration</div>
                            <div class="mb-group">
                                <div class="mb-link-row">
                                    <a class="mb-link {{ $adminOpen ? 'active-soft' : '' }}" href="{{ route('admin.dashboard') }}" title="Admin">
                                        <i class="bi bi-shield-lock"></i><span class="mb-label">Admin</span>
                                    </a>
                                    <button class="mb-toggle mb-chevron" type="button" data-bs-toggle="collapse" data-bs-target="#adminMenu"
                                            aria-expanded="{{ $adminOpen ? 'true' : 'false' }}" aria-controls="adminMenu" aria-label="Toggle admin menu">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                </div>
                                <div class="collapse mb-sub {{ $adminOpen ? 'show' : '' }}" id="adminMenu">
                                    @foreach ([
                                        ['admin.dashboard', 'admin.dashboard', 'Overview'],
                                        ['admin.users.index', 'admin.users.*', 'Users'],
                                        ['admin.smtp.index', 'admin.smtp.*', 'SMTP accounts'],
                                        ['admin.campaigns.index', 'admin.campaigns.*', 'Campaigns'],
                                        ['admin.logs.index', 'admin.logs.index', 'Email logs'],
                                        ['admin.logs.failed', 'admin.logs.failed', 'Failed emails'],
                                        ['admin.settings.edit', 'admin.settings.*', 'Settings'],
                                    ] as [$route, $pattern, $label])
                                        <a class="{{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">{{ $label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </nav>
                </div>

                <div class="mb-usercard">
                    <a href="{{ route('account.edit') }}" class="mb-usercard-link" title="Account">
                        <span class="mb-avatar" style="background: hsl({{ $hue }} 70% 52%)">{{ $initials }}</span>
                        <span class="mb-label min-w-0">
                            <span class="d-block fw-semibold text-truncate">{{ $authUser->name }}</span>
                            <span class="d-block small text-body-secondary">{{ $authUser->isAdmin() ? 'Admin' : 'User' }}</span>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="mb-label">@csrf
                        <button type="submit" class="btn btn-sm btn-icon" title="Sign out" aria-label="Sign out"><i class="bi bi-box-arrow-right"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    {{-- ============ Main ============ --}}
    <div class="mb-main">
        <header class="mb-topbar">
            <button type="button" class="btn btn-icon" id="sbToggle" aria-label="Toggle menu" aria-controls="sidebar"><i class="bi bi-list fs-4"></i></button>

            <form method="GET" action="{{ route('campaigns.index') }}" class="mb-search d-none d-md-block" role="search">
                <i class="bi bi-search"></i>
                <input type="search" name="q" class="form-control" placeholder="Search campaigns…" aria-label="Search campaigns" value="{{ request()->routeIs('campaigns.index') ? request('q') : '' }}">
            </form>

            <div class="ms-auto d-flex align-items-center gap-2">
                @if ($workerActive)
                    <span class="mb-live d-none d-sm-inline-flex" title="A campaign is sending emails"><span class="mb-dot"></span>Sending</span>
                @endif
                <button type="button" class="btn btn-icon" id="themeToggle" aria-label="Switch light/dark theme"><i class="bi bi-moon-stars"></i></button>
                <a class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" href="{{ route('campaigns.create') }}">
                    <i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline">New campaign</span>
                </a>
                <div class="dropdown">
                    <button class="btn p-0 border-0 rounded-circle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                        <span class="mb-avatar" style="background: hsl({{ $hue }} 70% 52%)">{{ $initials }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text"><span class="d-block fw-semibold">{{ $authUser->name }}</span><span class="small text-body-secondary">{{ $authUser->email }}</span></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('account.edit') }}"><i class="bi bi-person-gear me-2"></i>Account</a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main id="main" class="mb-content" tabindex="-1">
            <x-alert-messages />
            @yield('content')
        </main>

        <footer class="mb-footer">&copy; {{ date('Y') }} {{ config('mailbatch.name') }} · {{ config('mailbatch.tagline') }}</footer>
    </div>
</div>

{{-- Shared delete-confirmation modal --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="confirmForm" class="modal-content">
            @csrf @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalTitle">Please confirm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script src="{{ asset('assets/js/worker.js') }}"></script>
<script src="{{ asset('assets/js/layout.js') }}"></script>
@stack('scripts')
</body>
</html>

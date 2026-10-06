<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    @foreach ([
        ['admin.dashboard', 'admin.dashboard', 'Overview'],
        ['admin.users.index', 'admin.users.*', 'Users'],
        ['admin.smtp.index', 'admin.smtp.*', 'SMTP accounts'],
        ['admin.campaigns.index', 'admin.campaigns.*', 'Campaigns'],
        ['admin.logs.index', 'admin.logs.index', 'Email logs'],
        ['admin.logs.failed', 'admin.logs.failed', 'Failed emails'],
        ['admin.settings.edit', 'admin.settings.*', 'Settings'],
    ] as [$route, $pattern, $label])
        <li class="nav-item"><a class="nav-link py-1 {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">{{ $label }}</a></li>
    @endforeach
</ul>

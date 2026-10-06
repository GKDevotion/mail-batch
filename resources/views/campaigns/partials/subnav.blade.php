<ul class="nav nav-pills mb-3 flex-wrap gap-1 align-items-center">
    @foreach ([
        ['campaigns.show', 'Details', 'bi-card-text'],
        ['campaigns.confirm', 'Send', 'bi-send'],
        ['campaigns.progress', 'Progress', 'bi-activity'],
        ['campaigns.recipients', 'Recipients', 'bi-people'],
        ['campaigns.logs', 'Logs', 'bi-journal-text'],
    ] as [$route, $label, $icon])
        <li class="nav-item">
            <a class="nav-link py-1 {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route, $campaign) }}">
                <i class="bi {{ $icon }} me-1"></i>{{ $label }}
            </a>
        </li>
    @endforeach
    <li class="nav-item ms-lg-auto">
        <a class="btn btn-outline-success btn-sm" href="{{ route('campaigns.export', $campaign) }}">
            <i class="bi bi-file-earmark-excel me-1"></i>Export results
        </a>
    </li>
</ul>

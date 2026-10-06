<ul class="list-group list-group-flush">
    @foreach ($steps as $i => $step)
        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <i class="bi {{ $step['done'] ? 'bi-check-circle-fill text-success' : 'bi-circle text-body-secondary' }}"></i>
                <span class="text-truncate">{{ $i + 1 }}. {{ $step['label'] }}</span>
            </div>
            @if (Route::has($step['route']))
                <a href="{{ route($step['route'], $campaign) }}" class="btn btn-sm {{ $step['done'] ? 'btn-outline-secondary' : 'btn-primary' }}">
                    {{ $step['done'] ? 'Review' : 'Start' }}
                </a>
            @else
                <span class="badge text-bg-light border">Phase {{ $step['phase'] }}</span>
            @endif
        </li>
    @endforeach
</ul>

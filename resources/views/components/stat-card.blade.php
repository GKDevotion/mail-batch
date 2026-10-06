@props(['stat', 'label', 'value', 'icon', 'color' => '#5b47f5', 'series' => null, 'pct' => null])
<div class="col-6 col-lg-4 col-xxl-2">
    <div class="card mb-stat h-100" style="--c: {{ $color }}">
        <div class="card-body pb-2">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <div class="text-body-secondary small text-truncate">{{ $label }}</div>
                    <div class="mb-stat-value" data-stat="{{ $stat }}">{{ number_format($value) }}</div>
                </div>
                <span class="mb-stat-icon"><i class="bi bi-{{ $icon }}"></i></span>
            </div>
            @if ($pct !== null)
                <div class="mb-meter mt-3" role="progressbar" aria-label="{{ $label }} share" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: {{ $pct }}%"></span>
                </div>
                <div class="small text-body-secondary mt-1">{{ $pct }}% of all recipients</div>
            @endif
        </div>
        @if (is_array($series))
            <x-sparkline :values="$series" :color="$color" />
        @endif
    </div>
</div>

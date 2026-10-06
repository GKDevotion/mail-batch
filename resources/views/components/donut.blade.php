@props(['segments' => [], 'centerValue' => '', 'centerLabel' => ''])
@php
    $total = max((int) array_sum(array_column($segments, 'value')), 0);
    $r = 70;
    $c = 2 * M_PI * $r;
    $offset = 0;
@endphp
<div class="mb-donut-wrap">
    <div class="mb-donut position-relative mx-auto">
        <svg viewBox="0 0 180 180" role="img" aria-label="Recipient status breakdown">
            <circle cx="90" cy="90" r="{{ $r }}" fill="none" class="mb-donut-track" stroke-width="20"/>
            @if ($total > 0)
                @foreach ($segments as $seg)
                    @if ($seg['value'] > 0)
                        @php($len = $c * $seg['value'] / $total)
                        <circle cx="90" cy="90" r="{{ $r }}" fill="none" stroke="{{ $seg['color'] }}" stroke-width="20"
                                stroke-dasharray="{{ round($len, 2) }} {{ round($c - $len, 2) }}" stroke-dashoffset="{{ round(-$offset, 2) }}"
                                transform="rotate(-90 90 90)"><title>{{ $seg['label'] }}: {{ number_format($seg['value']) }}</title></circle>
                        @php($offset += $len)
                    @endif
                @endforeach
            @endif
        </svg>
        <div class="mb-donut-center">
            <div class="fs-3 fw-bold lh-1">{{ $centerValue }}</div>
            <div class="small text-body-secondary">{{ $centerLabel }}</div>
        </div>
    </div>
    <ul class="list-unstyled mb-0 mt-3 small">
        @foreach ($segments as $seg)
            <li class="d-flex align-items-center justify-content-between py-1">
                <span class="d-inline-flex align-items-center gap-2"><span class="mb-legend-dot" style="background: {{ $seg['color'] }}"></span>{{ $seg['label'] }}</span>
                <span class="fw-semibold">{{ number_format($seg['value']) }}
                    <span class="text-body-secondary fw-normal">({{ $total > 0 ? round($seg['value'] / $total * 100) : 0 }}%)</span></span>
            </li>
        @endforeach
    </ul>
</div>

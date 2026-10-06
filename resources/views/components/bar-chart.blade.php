@props(['labels' => [], 'a' => [], 'b' => [], 'aLabel' => 'Sent', 'bLabel' => 'Failed', 'aColor' => '#5b47f5', 'bColor' => '#ef4444'])
@php
    $n = max(count($labels), 1);
    $W = 640; $H = 250; $padL = 38; $padR = 8; $padT = 12; $padB = 30;
    $max = max(max($a ?: [0]), max($b ?: [0]), 1);
    $mag = 10 ** floor(log10($max));
    $nice = (int) max(4, ceil($max / $mag) * $mag);
    $nice = (int) (ceil($nice / 4) * 4);
    $plotW = $W - $padL - $padR;
    $plotH = $H - $padT - $padB;
    $slot = $plotW / $n;
    $barW = min(14, $slot * 0.32);
    $every = max(1, (int) ceil($n / 7));
@endphp
<div class="mb-chart-wrap">
    <svg class="mb-chart" viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ $aLabel }} and {{ $bLabel }} per day">
        @for ($k = 0; $k <= 4; $k++)
            @php
                $y = $padT + $plotH - $plotH * $k / 4;
            @endphp
            <line x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y }}" y2="{{ $y }}" class="mb-grid"/>
            <text x="{{ $padL - 8 }}" y="{{ $y + 4 }}" text-anchor="end" class="mb-axis">{{ number_format($nice * $k / 4) }}</text>
        @endfor

        @foreach ($labels as $i => $label)
            @php
                $cx = $padL + $slot * ($i + 0.5);
                $ha = (($a[$i] ?? 0) / $nice) * $plotH;
                $hb = (($b[$i] ?? 0) / $nice) * $plotH;
            @endphp
            <rect class="mb-bar" style="--i: {{ $i }}" x="{{ round($cx - $barW - 1, 1) }}" y="{{ round($padT + $plotH - $ha, 1) }}" width="{{ round($barW, 1) }}" height="{{ round($ha, 1) }}" rx="3" fill="{{ $aColor }}"><title>{{ $label }}: {{ $a[$i] ?? 0 }} {{ strtolower($aLabel) }}</title></rect>

            <rect class="mb-bar" style="--i: {{ $i }}" x="{{ round($cx + 1, 1) }}" y="{{ round($padT + $plotH - $hb, 1) }}" width="{{ round($barW, 1) }}" height="{{ round($hb, 1) }}" rx="3" fill="{{ $bColor }}"><title>{{ $label }}: {{ $b[$i] ?? 0 }} {{ strtolower($bLabel) }}</title></rect>

            @if ($i % $every === 0)
                <text x="{{ round($cx, 1) }}" y="{{ $H - 8 }}" text-anchor="middle" class="mb-axis">{{ $label }}</text>
            @endif
        @endforeach
    </svg>
    <div class="d-flex flex-wrap gap-3 justify-content-center small mt-2">
        <span class="d-inline-flex align-items-center gap-1"><span class="mb-legend-dot" style="background: {{ $aColor }}"></span>{{ $aLabel }}</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="mb-legend-dot" style="background: {{ $bColor }}"></span>{{ $bLabel }}</span>
    </div>
</div>

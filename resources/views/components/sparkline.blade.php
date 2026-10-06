@props(['values' => [], 'color' => '#5b47f5', 'height' => 56])
@php
    $vals = array_values(array_map('intval', $values));
    if (count($vals) < 2) {
        $vals = [0, 0];
    }
    $n = count($vals);
    $w = 300;
    $h = (int) $height;
    $max = max(max($vals), 1);
    $uid = 'sp'.substr(md5(json_encode($vals).$color.mt_rand()), 0, 8);

    $pts = [];
    foreach ($vals as $i => $v) {
        $pts[] = [round($i * $w / ($n - 1), 1), round($h - 4 - ($v / $max) * ($h - 12), 1)];
    }

    $d = 'M'.$pts[0][0].','.$pts[0][1];
    for ($i = 1; $i < $n; $i++) {
        $xm = round(($pts[$i - 1][0] + $pts[$i][0]) / 2, 1);
        $d .= ' C'.$xm.','.$pts[$i - 1][1].' '.$xm.','.$pts[$i][1].' '.$pts[$i][0].','.$pts[$i][1];
    }
    $area = $d.' L'.$w.','.$h.' L0,'.$h.' Z';
@endphp
<svg class="mb-spark" viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" role="img" aria-label="Trend over the last {{ $n }} days">
    <defs>
        <linearGradient id="{{ $uid }}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="{{ $color }}" stop-opacity=".30"/>
            <stop offset="1" stop-color="{{ $color }}" stop-opacity="0"/>
        </linearGradient>
    </defs>
    <path d="{{ $area }}" fill="url(#{{ $uid }})"/>
    <path d="{{ $d }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
</svg>

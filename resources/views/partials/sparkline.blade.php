@props(['data' => [], 'width' => 200, 'height' => 40, 'color' => '#6366f1', 'label' => ''])

@php
    $values = array_values($data);
    $hasData = count($values) >= 2;

    if ($hasData) {
        $max = max($values) ?: 1;
        $min = min($values);
        $range = $max - $min;
        if ($range == 0) { $range = 1; $min = $min - 0.5; }
        $n = count($values);
        $padding = 1;
        $w = $width - $padding * 2;
        $h = $height - $padding * 2;

        $points = '';
        foreach ($values as $i => $val) {
            $x = $padding + ($n > 1 ? ($i / ($n - 1)) * $w : $w / 2);
            $y = $padding + $h - (($val - $min) / $range) * $h;
            $points .= number_format($x, 1) . ',' . number_format($y, 1) . ' ';
        }

        $polygonPoints = number_format($padding, 1) . ',' . ($padding + $h) . ' ' . $points . number_format($padding + $w, 1) . ',' . ($padding + $h);
    }
@endphp

<div class="flex flex-col gap-1">
    @if($label)
        <span class="text-[11px] text-slate-400 font-medium">{{ $label }}</span>
    @endif
    <svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" class="block overflow-visible">
        @if($hasData)
            <polygon points="{{ trim($polygonPoints) }}" fill="{{ $color }}" opacity="0.1" />
            <polyline points="{{ trim($points) }}" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        @else
            <line x1="0" y1="{{ $height / 2 }}" x2="{{ $width }}" y2="{{ $height / 2 }}" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="4,4" />
        @endif
    </svg>
    @if($hasData)
        <div class="flex justify-between text-[10px] text-slate-400">
            <span>{{ $min }}</span>
            <span>{{ $max }}</span>
        </div>
    @endif
</div>

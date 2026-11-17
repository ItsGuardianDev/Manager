@props(['title', 'data'])
@php
    $palette = ['#2a98a0', '#2d333b', '#7ccfd5', '#8b95a1', '#aee4e8', '#1f636a', '#cbd5e1'];
    $slices = collect($data)->filter(fn ($count) => $count > 0);
    $total = $slices->sum();
    $point = fn (float $angle) => [round(20 + 15 * cos($angle), 3), round(20 + 15 * sin($angle), 3)];
    $angle = -M_PI / 2;
    $paths = [];

    foreach ($slices->values() as $index => $count) {
        $sweep = $count / $total * 2 * M_PI;
        [$x1, $y1] = $point($angle);
        [$x2, $y2] = $point($angle + $sweep);
        $paths[] = ['label' => $slices->keys()[$index], 'count' => $count, 'color' => $palette[$index % count($palette)], 'full' => $slices->count() === 1, 'd' => "M {$x1} {$y1} A 15 15 0 ".($sweep > M_PI ? 1 : 0)." 1 {$x2} {$y2}"];
        $angle += $sweep;
    }
@endphp
<x-card {{ $attributes }}>
    <h3 class="mb-3 text-sm font-semibold text-ink">{{ $title }}</h3>
    @if ($total === 0)
        <p class="text-sm text-slate-500">Geen gegevens</p>
    @else
        <div class="flex items-center gap-4">
            <svg viewBox="0 0 40 40" class="size-24 shrink-0" role="img" aria-label="{{ $title }}">
                <circle cx="20" cy="20" r="15" fill="none" stroke="#f1f5f9" stroke-width="6" />
                @foreach ($paths as $path)
                    @if ($path['full'])
                        <circle cx="20" cy="20" r="15" fill="none" stroke="{{ $path['color'] }}" stroke-width="6"><title>{{ $path['label'] }}: {{ $path['count'] }}</title></circle>
                    @else
                        <path d="{{ $path['d'] }}" fill="none" stroke="{{ $path['color'] }}" stroke-width="6"><title>{{ $path['label'] }}: {{ $path['count'] }}</title></path>
                    @endif
                @endforeach
                <text x="20" y="21.5" text-anchor="middle" font-size="7" font-weight="700" fill="#2d333b">{{ $total }}</text>
            </svg>
            <ul class="max-h-24 min-w-0 flex-1 space-y-1 overflow-y-auto text-xs text-slate-600">
                @foreach ($slices as $label => $count)
                    <li class="flex items-center gap-2"><span class="size-2.5 shrink-0 rounded-full" style="background: {{ $palette[$loop->index % count($palette)] }}"></span><span class="truncate" title="{{ $label }}">{{ $label }}</span><span class="ml-auto font-medium text-ink">{{ $count }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</x-card>

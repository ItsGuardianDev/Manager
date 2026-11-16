@props(['title', 'data'])
@php
    $palette = ['#2a98a0', '#2d333b', '#7ccfd5', '#8b95a1', '#aee4e8', '#1f636a', '#cbd5e1'];
    $total = array_sum($data);
    $circumference = 2 * M_PI * 15;
    $offset = 0;
@endphp
<x-card {{ $attributes }}>
    <h3 class="mb-3 text-sm font-semibold text-ink">{{ $title }}</h3>
    @if ($total === 0)
        <p class="text-sm text-slate-500">Geen gegevens</p>
    @else
        <div class="flex items-center gap-4">
            <svg viewBox="0 0 40 40" class="size-24 shrink-0" role="img" aria-label="{{ $title }}">
                <g transform="rotate(-90 20 20)">
                    <circle cx="20" cy="20" r="15" fill="none" stroke="#f1f5f9" stroke-width="6" />
                    @foreach (array_values($data) as $index => $count)
                        @php($length = $count / $total * $circumference)
                        <circle cx="20" cy="20" r="15" fill="none" stroke="{{ $palette[$index % count($palette)] }}" stroke-width="6"
                                stroke-dasharray="{{ $length }} {{ $circumference - $length }}" stroke-dashoffset="{{ -$offset }}"><title>{{ array_keys($data)[$index] }}: {{ $count }}</title></circle>
                        @php($offset += $length)
                    @endforeach
                </g>
                <text x="20" y="21.5" text-anchor="middle" font-size="7" font-weight="700" fill="#2d333b">{{ $total }}</text>
            </svg>
            <ul class="max-h-24 min-w-0 flex-1 space-y-1 overflow-y-auto text-xs text-slate-600">
                @foreach ($data as $label => $count)
                    <li class="flex items-center gap-2"><span class="size-2.5 shrink-0 rounded-full" style="background: {{ $palette[$loop->index % count($palette)] }}"></span><span class="truncate" title="{{ $label }}">{{ $label }}</span><span class="ml-auto font-medium text-ink">{{ $count }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</x-card>

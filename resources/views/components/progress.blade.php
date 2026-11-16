@props(['value' => 0, 'label' => true])
@php
    $percent = (int) $value;
    $color = $percent > 100 ? 'bg-rose-500' : ($percent >= 85 ? 'bg-amber-500' : 'bg-brand-500');
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="h-2.5 min-w-16 flex-1 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full rounded-full {{ $color }}" style="width: {{ min(100, max(0, $percent)) }}%"></div>
    </div>
    @if ($label)<span class="w-10 text-right text-xs tabular-nums {{ $percent > 100 ? 'font-semibold text-rose-600' : 'text-slate-600' }}">{{ $percent }}%</span>@endif
</div>

@props(['name', 'label', 'type' => 'text', 'value' => null, 'keep' => true, 'hint' => null, 'inline' => false])
@php($current = $keep ? old($name, $value) : null)
<div {{ $attributes->only('class')->merge(['class' => $inline ? 'grid grid-cols-[8.5rem_1fr] items-start gap-x-4' : '']) }}>
    <label for="{{ $name }}" class="{{ $inline ? 'pt-2' : 'mb-1 block' }} text-sm font-medium text-slate-700">{{ $label }}@if ($attributes->has('required'))<span class="text-rose-500"> *</span>@endif</label>
    <div class="min-w-0">
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}"
               {{ $attributes->except('class') }}
               class="block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset {{ $errors->has($name) ? 'ring-rose-400' : 'ring-slate-300' }} placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-600">
        @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
        @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
</div>

@props(['name', 'label' => null, 'placeholder' => null, 'options', 'selected' => null, 'bare' => false, 'inline' => false])
@php($current = $bare ? request($name) : old($name, $selected))
<div {{ $attributes->only('class')->merge(['class' => $inline ? 'grid grid-cols-[8.5rem_1fr] items-start gap-x-4' : '']) }}>
    @if ($label)<label for="{{ $name }}" class="{{ $inline ? 'pt-2' : 'mb-1 block' }} text-sm font-medium text-slate-700">{{ $label }}</label>@endif
    <div class="min-w-0">
        <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->except('class') }}
                class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset {{ $errors->has($name) ? 'ring-rose-400' : 'ring-slate-300' }} focus:ring-2 focus:ring-inset focus:ring-brand-600">
            @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $text }}</option>
            @endforeach
        </select>
        @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
</div>

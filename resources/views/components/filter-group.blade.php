@props(['title', 'name', 'options', 'type' => 'checkbox'])
<fieldset class="mb-6">
    <legend class="mb-2 text-sm font-semibold text-slate-900">{{ $title }}</legend>
    <div class="space-y-1.5">
        @foreach ($options as $value => $label)
            @php($checked = $type === 'radio' ? (string) request($name, 'active') === (string) $value : in_array((string) $value, array_map('strval', (array) request($name, [])), true))
            <label class="flex items-center gap-2 text-sm text-slate-600">
                @if ($type === 'radio')
                    <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked($checked) class="border-slate-300 text-brand-600">
                @else
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $value }}" @checked($checked) class="rounded border-slate-300 text-brand-600">
                @endif
                {{ $label }}
            </label>
        @endforeach
    </div>
</fieldset>

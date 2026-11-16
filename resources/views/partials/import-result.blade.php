@php($result = session('import_result'))
@if ($result)
    <div class="mb-6 space-y-3 rounded-xl border border-slate-200 bg-white p-5 text-sm shadow-sm">
        <p class="font-semibold">
            Import afgerond: {{ $result['created'] }} aangemaakt{{ isset($result['linked']) ? ", {$result['linked']} gekoppeld" : '' }},
            {{ count($result['skipped']) }} overgeslagen, {{ count($result['errors']) }} met fouten.
        </p>
        @if ($result['skipped'])
            <div><p class="font-medium text-amber-700">Overgeslagen (dubbelen)</p><ul class="mt-1 list-disc pl-5 text-slate-600">@foreach ($result['skipped'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
        @endif
        @if ($result['errors'])
            <div><p class="font-medium text-rose-700">Fouten</p><ul class="mt-1 list-disc pl-5 text-slate-600">@foreach ($result['errors'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
        @endif
    </div>
@endif

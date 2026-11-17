@extends('layouts.app')
@section('title', 'Diensten importeren')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="shrink-0"><a href="{{ route('services.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Diensten</a><h1 class="mt-1 text-2xl font-semibold tracking-tight">Diensten importeren</h1></div>
<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-2">
    <x-card title="Upload een Excel- of CSV-bestand">
        <form method="POST" action="{{ route('services.import.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div><input type="file" name="file" accept=".csv,.txt,.xlsx" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-medium hover:file:bg-slate-200">
            <p class="text-sm text-slate-600">Een export uit Simplicate (.xlsx) kan direct worden geüpload: Nederlandse kolomnamen en waarden zoals "Klant", "Ja" of "01-03-2024" worden herkend. Alleen het eerste werkblad wordt gelezen.</p>@error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            <p class="text-sm text-slate-600">Eén regel per urenregel of kostenregel: de gegevens van de dienst herhalen zich, regels met dezelfde naam vormen één dienst. Een bestaande dienst (op naam) wordt bijgewerkt en krijgt de regels uit het bestand; nieuwe diensten worden aangemaakt. Onbekende urensoorten, kostensoorten en omzetgroepen worden aangemaakt. Sla een Excel-bestand op als CSV (UTF-8, gescheiden door puntkomma).</p>
            <div class="flex items-center gap-3"><button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Importeren</button><a href="{{ route('services.import.template') }}" class="text-sm font-medium text-brand-700 hover:underline">Sjabloon downloaden</a></div>
        </form>
        <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500"><span class="font-medium">Kolommen:</span> {{ implode(', ', $fields) }}<br><span class="font-medium">invoice_method:</span> {{ implode(', ', $methods) }}<br><span class="font-medium">billing_cycle:</span> {{ implode(', ', $cycles) }}<br><span class="font-medium">vat_code:</span> {{ implode(', ', $vatCodes) }}</p>
    </x-card>
    <x-card title="Resultaat">
        @php
            $result = session('import_result');
        @endphp
        @if ($result)
            <div class="space-y-3 text-sm">
                <p class="font-semibold">{{ $result['created'] }} diensten aangemaakt, {{ $result['updated'] }} bijgewerkt, {{ count($result['errors']) }} fouten.</p>
                @if ($result['errors'])<div><p class="font-medium text-rose-700">Fouten</p><ul class="list-disc pl-5 text-slate-600">@foreach ($result['errors'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>@endif
            </div>
        @else
            <p class="text-sm text-slate-500">Na het importeren zie je hier wat is aangemaakt.</p>
        @endif
    </x-card>
</div>
</div>
@endsection

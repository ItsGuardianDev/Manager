@extends('layouts.app')
@section('title', 'Inkoopboeking')
@section('content')
@php
    $input = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $rows = array_values(old('lines', [[], [], []]));
    $services = $projects->flatMap(fn ($p) => $p->services->where('is_active', true)->map(fn ($s) => ['id' => $s->id, 'name' => $p->name.' — '.$s->name]));
@endphp
<form method="POST" action="{{ route('purchases.store') }}" class="flex min-h-full flex-col gap-4 p-4 sm:p-5 lg:h-full">
    @csrf
    <div class="flex shrink-0 items-center justify-between"><div><a href="{{ route('purchases.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Projectinkoop</a><h1 class="text-2xl font-semibold tracking-tight">Inkoopboeking</h1></div><button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">Opslaan</button></div>
    <div class="min-h-0 space-y-6 overflow-y-auto rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:flex-1">
        <div class="grid max-w-3xl gap-4 sm:grid-cols-2">
            <x-select name="project_id" label="Project" placeholder="— Kies een project —" :options="$projects->pluck('name', 'id')" :selected="$selected" />
            <x-select name="supplier_id" label="Crediteur" placeholder="— Geen —" :options="$suppliers" />
            <x-field name="date" label="Datum" type="date" :value="today()->format('Y-m-d')" required />
            <x-select name="type" label="Type" :options="['invoice' => 'Inkoopfactuur', 'expense' => 'Onkosten']" />
            <x-field name="description" label="Omschrijving" required class="sm:col-span-2" />
            <x-field name="source" label="Bron (factuurnummer)" class="sm:col-span-2" />
        </div>
        <section data-rows>
            <h2 class="mb-2 text-base font-semibold text-brand-700">Regels</h2>
            @error('lines')<p class="mb-2 text-xs text-rose-600">{{ $message }}</p>@enderror
            <table class="w-full max-w-5xl text-sm"><thead><tr class="bg-amber-50 text-left text-xs font-semibold text-slate-700"><th class="px-3 py-2">Dienst</th><th class="px-3 py-2">Kostensoort</th><th class="px-3 py-2">Omschrijving</th><th class="px-3 py-2">Aantal</th><th class="px-3 py-2">Prijs (€)</th></tr></thead>
                <tbody data-rows-body>
                @foreach ($rows as $i => $row)
                    <tr><td class="px-1 py-1"><select name="lines[{{ $i }}][project_service_id]" class="{{ $input }}"><option value="">— Geen dienst —</option>@foreach ($services as $service)<option value="{{ $service['id'] }}" @selected((string) ($row['project_service_id'] ?? '') === (string) $service['id'])>{{ $service['name'] }}</option>@endforeach</select></td>
                        <td class="px-1 py-1"><select name="lines[{{ $i }}][cost_type_id]" class="{{ $input }}"><option value="">—</option>@foreach ($costTypes as $id => $name)<option value="{{ $id }}" @selected((string) ($row['cost_type_id'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select></td>
                        <td class="px-1 py-1"><input name="lines[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" class="{{ $input }}"></td>
                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" placeholder="1" class="{{ $input }}"></td>
                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_price]" value="{{ $row['unit_price'] ?? '' }}" class="{{ $input }}"></td></tr>
                @endforeach
                </tbody></table>
            <button type="button" data-add-row class="mt-2 text-sm font-medium text-brand-700 hover:underline">+ Regel toevoegen</button>
            <p class="mt-1 text-xs text-slate-500">Alleen regels met een prijs worden opgeslagen. Kies bij elke regel de dienst waarop de kosten worden geboekt.</p>
        </section>
    </div>
</form>
@endsection

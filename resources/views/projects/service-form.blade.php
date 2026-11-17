@extends('layouts.app')
@section('title', $service->exists ? $service->name : 'Nieuwe dienst')
@section('content')
@php
    $editing = $service->exists;
    $input = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $hourRows = old('hour_lines', $editing ? $service->hourLines->map(fn ($l) => ['hour_type_id' => $l->hour_type_id, 'hours' => $l->hours, 'rate' => $l->rate])->all() : $templateHourRows);
    $costRows = old('cost_lines', $editing ? $service->costLines->map(fn ($l) => ['cost_type_id' => $l->cost_type_id, 'quantity' => $l->quantity, 'purchase_price' => $l->purchase_price, 'margin_percentage' => $l->margin_percentage])->all() : $templateCostRows);
    $origin = $template ?? $service->defaultService;
    $priceLocked = $origin && ! $origin->price_editable && $origin->price !== null;
    $flag = fn (string $name) => (bool) old($name, $service->{$name} ?? true);
    $hourRows = array_values($hourRows);
    $costRows = array_values($costRows);
    while (count($hourRows) < 2) { $hourRows[] = []; }
    $hourRows[] = [];
    while (count($costRows) < 2) { $costRows[] = []; }
    $costRows[] = [];
    $method = old('invoice_method', $service->invoice_method?->value ?? 'fixed_price');
@endphp
<form method="POST" action="{{ $editing ? route('projects.services.update', [$project, $service]) : route('projects.services.store', $project) }}" class="flex min-h-full flex-col gap-4 p-4 sm:p-5 lg:h-full">
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('projects.show', [$project, 'tab' => 'services']) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $project->name }}</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $editing ? $service->name : 'Nieuwe dienst' }}</h1>
        </div>
        <div class="flex gap-2"><a href="{{ route('projects.show', [$project, 'tab' => 'services']) }}" class="rounded-full px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white">Annuleren</a><button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">Opslaan</button></div>
    </div>

    <div class="min-h-0 space-y-8 overflow-y-auto rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:flex-1">
        <section class="grid gap-x-10 gap-y-4 lg:grid-cols-3">
            <div class="space-y-4">
                <h2 class="text-base font-semibold text-brand-700">Eigenschappen</h2>
                @if ($editing)
                    <input type="hidden" name="default_service_id" value="{{ $service->default_service_id }}">
                    @if ($service->defaultService)<p class="text-xs text-slate-500">Gebaseerd op standaarddienst <span class="font-medium">{{ $service->defaultService->name }}</span>@if ($service->defaultService->is_blocked) (inmiddels geblokkeerd, deze dienst blijft bestaan)@endif.</p>@endif
                @else
                    <div><label class="mb-1 block text-sm font-medium text-slate-700" for="default_service_id">Standaarddienst</label>
                        <select id="default_service_id" name="default_service_id" data-default-service class="{{ $input }}"><option value="">Zonder standaarddienst</option>@foreach ($defaultServices as $default)<option value="{{ $default->id }}" @selected((string) old('default_service_id', $service->default_service_id) === (string) $default->id)>{{ $default->name }}</option>@endforeach</select>
                        @if ($template)<p class="mt-1 text-xs text-slate-500">Prijs, budgetten en instellingen zijn overgenomen. Je kunt ze hieronder aanpassen voor dit project.</p>@endif</div>
                @endif
                <x-field name="name" label="Omschrijving" :value="$service->name" required />
                <x-select name="invoice_method" label="Factuurmethode" :options="$methods" :selected="$method" data-method-select />
                <div data-price-field class="{{ in_array($method, ['fixed_price', 'subscription'], true) ? '' : 'hidden' }}"><x-field name="price" label="Prijs (€, excl. btw)" type="number" step="0.01" min="0" :value="$service->price" :readonly="$priceLocked" :hint="$priceLocked ? 'De prijs van deze standaarddienst kan niet worden aangepast.' : null" /><div data-cycle-field class="mt-4 {{ $method === 'subscription' ? '' : 'hidden' }}"><x-select name="billing_cycle" label="Facturatieperiode" :options="$cycles" :selected="$service->billing_cycle?->value ?? 'monthly'" /></div></div>
                <div class="grid grid-cols-2 gap-3"><x-field name="start_date" label="Startdatum" type="date" :value="$service->start_date?->format('Y-m-d')" /><x-field name="end_date" label="Einddatum" type="date" :value="$service->end_date?->format('Y-m-d')" /></div>
            </div>
            <div class="space-y-4">
                <h2 class="select-none text-base font-semibold text-transparent" aria-hidden="true">.</h2>
                <div><label for="description" class="mb-1 block text-sm font-medium text-slate-700">Beschrijving</label><textarea id="description" name="description" rows="8" class="{{ $input }}">{{ old('description', $service->description) }}</textarea></div>
                <x-field name="invoice_from" label="Factureren vanaf" type="date" :value="$service->invoice_from?->format('Y-m-d')" />
            </div>
            <div class="space-y-4">
                <h2 class="text-base font-semibold text-brand-700">Uitgebreide opties</h2>
                <x-select name="revenue_group_id" label="Omzetgroep" placeholder="Kies een omzetgroep" :options="$revenueGroups" :selected="$service->revenue_group_id" />
                <x-select name="vat_code" label="Btw-code" placeholder="Kies een btw-code" :options="App\Models\Organization::VAT_CODES" :selected="$service->vat_code" />
                <x-field name="discount_percentage" label="Korting (%)" type="number" step="0.01" min="0" max="100" :value="$service->discount_percentage" hint="Geldt voor de prijs en het urenbudget." />
                <x-field name="completion_percentage" label="Voltooid (%)" type="number" min="0" max="100" :value="$service->completion_percentage" />
                <div class="space-y-2 text-sm">@foreach (['allow_hours' => 'Uren mogen worden geboekt', 'allow_costs' => 'Inkoop en kosten mogen worden geboekt', 'is_plannable' => 'Inplanbaar in de capaciteitsplanning'] as $name => $label)<label class="flex items-center gap-2"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked($flag($name)) class="rounded border-slate-300 text-brand-600"> {{ $label }}</label>@endforeach</div>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active)) class="rounded border-slate-300 text-brand-600"> Dienst is actief</label>
            </div>
        </section>

        <section data-rows>
            <h2 class="mb-2 text-base font-semibold text-brand-700">Urenregistratie</h2>
            <table class="w-full max-w-3xl text-sm"><thead><tr class="bg-amber-50 text-left text-xs font-semibold text-slate-700"><th class="px-3 py-2">Urensoort</th><th class="px-3 py-2">Aantal uren</th><th class="px-3 py-2">Uurtarief (€)</th></tr></thead>
                <tbody data-rows-body>
                @foreach ($hourRows as $i => $row)
                    <tr><td class="px-1 py-1"><select name="hour_lines[{{ $i }}][hour_type_id]" class="{{ $input }}"><option value="">—</option>@foreach ($hourTypes as $type)<option value="{{ $type->id }}" @selected((string) ($row['hour_type_id'] ?? '') === (string) $type->id)>{{ $type->name }} (€ {{ number_format($type->default_rate, 2, ',', '.') }})</option>@endforeach</select></td>
                        <td class="px-1 py-1"><input type="number" step="0.25" min="0" name="hour_lines[{{ $i }}][hours]" value="{{ $row['hours'] ?? '' }}" class="{{ $input }}"></td>
                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" name="hour_lines[{{ $i }}][rate]" value="{{ $row['rate'] ?? '' }}" placeholder="standaard" class="{{ $input }}"></td></tr>
                @endforeach
                </tbody></table>
            <button type="button" data-add-row class="mt-2 text-sm font-medium text-brand-700 hover:underline">+ Voeg urensoort toe</button>
            <p class="mt-1 text-xs text-slate-500">Het urenbudget is het totaal van alle regels. Een leeg tarief gebruikt het standaardtarief van de urensoort.</p>
        </section>

        <section data-rows>
            <h2 class="mb-2 text-base font-semibold text-brand-700">Kostenregistratie</h2>
            <table class="w-full max-w-4xl text-sm"><thead><tr class="bg-amber-50 text-left text-xs font-semibold text-slate-700"><th class="px-3 py-2">Kostensoort</th><th class="px-3 py-2">Aantal</th><th class="px-3 py-2">Inkoopprijs (€)</th><th class="px-3 py-2">Marge (%)</th></tr></thead>
                <tbody data-rows-body>
                @foreach ($costRows as $i => $row)
                    <tr><td class="px-1 py-1"><select name="cost_lines[{{ $i }}][cost_type_id]" class="{{ $input }}"><option value="">—</option>@foreach ($costTypes as $type)<option value="{{ $type->id }}" @selected((string) ($row['cost_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>@endforeach</select></td>
                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" name="cost_lines[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" placeholder="1" class="{{ $input }}"></td>
                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" name="cost_lines[{{ $i }}][purchase_price]" value="{{ $row['purchase_price'] ?? '' }}" class="{{ $input }}"></td>
                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" name="cost_lines[{{ $i }}][margin_percentage]" value="{{ $row['margin_percentage'] ?? '' }}" placeholder="standaard" class="{{ $input }}"></td></tr>
                @endforeach
                </tbody></table>
            <button type="button" data-add-row class="mt-2 text-sm font-medium text-brand-700 hover:underline">+ Voeg kostensoort toe</button>
        </section>
    </div>
</form>
@endsection

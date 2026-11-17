@extends('layouts.app')
@section('title', $service->exists ? $service->name : 'Nieuwe dienst')
@section('content')
@php
    $editing = $service->exists;
    $canEdit = auth()->user()->can('projects.edit');
    $input = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $hourRows = array_values(old('hour_lines', $editing ? $service->hourLines->map(fn ($l) => ['hour_type_id' => $l->hour_type_id, 'hours' => $l->hours, 'rate' => $l->rate])->all() : []));
    $costRows = array_values(old('cost_lines', $editing ? $service->costLines->map(fn ($l) => ['cost_type_id' => $l->cost_type_id, 'quantity' => $l->quantity, 'purchase_price' => $l->purchase_price, 'margin_percentage' => $l->margin_percentage])->all() : []));
    while (count($hourRows) < 2) { $hourRows[] = []; }
    $hourRows[] = [];
    while (count($costRows) < 1) { $costRows[] = []; }
    $costRows[] = [];
    $method = old('invoice_method', $service->invoice_method?->value ?? 'after_calculation');
    $flag = fn (string $name) => (bool) old($name, $editing ? $service->{$name} : true);
@endphp
<form method="POST" action="{{ $editing ? route('services.update', $service) : route('services.store') }}" class="flex min-h-full flex-col gap-4 p-4 sm:p-5 lg:h-full">
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('services.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Diensten</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $editing ? $service->name : 'Nieuwe dienst' }}@if ($editing && $service->is_blocked)<span class="ml-2 align-middle rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Geblokkeerd</span>@endif</h1>
        </div>
        @if ($canEdit)<div class="flex gap-2"><a href="{{ route('services.index') }}" class="rounded-full px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white">Annuleren</a><button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">Opslaan</button></div>@endif
    </div>
    @if (session('status'))<div class="shrink-0 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>@endif
    @error('service')<div class="shrink-0 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $message }}</div>@enderror

    <fieldset @disabled(! $canEdit) class="min-h-0 space-y-8 overflow-y-auto rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:flex-1">
        <section class="grid gap-x-10 gap-y-4 lg:grid-cols-3">
            <div class="space-y-4">
                <h2 class="text-base font-semibold text-brand-700">Eigenschappen</h2>
                <x-field name="name" label="Naam" :value="$service->name" required />
                <x-select name="invoice_method" label="Factuurmethode" :options="$methods" :selected="$method" data-method-select />
                <div data-price-field class="space-y-4 {{ in_array($method, ['fixed_price', 'subscription'], true) ? '' : 'hidden' }}">
                    <x-field name="price" label="Standaardprijs (€, excl. btw)" type="number" step="0.01" min="0" :value="$service->price" />
                    <label class="flex items-start gap-2 text-sm"><input type="hidden" name="price_editable" value="0"><input type="checkbox" name="price_editable" value="1" @checked($flag('price_editable')) class="mt-0.5 rounded border-slate-300 text-brand-600"><span>Prijs mag worden aangepast bij het toevoegen aan een project<span class="block text-xs text-slate-500">Uit: elke projectdienst krijgt altijd deze prijs.</span></span></label>
                </div>
                <div data-cycle-field class="{{ $method === 'subscription' ? '' : 'hidden' }}"><x-select name="billing_cycle" label="Facturatieperiode" :options="$cycles" :selected="$service->billing_cycle?->value ?? 'monthly'" /></div>
            </div>
            <div class="space-y-4">
                <h2 class="select-none text-base font-semibold text-transparent" aria-hidden="true">.</h2>
                <div><label for="description" class="mb-1 block text-sm font-medium text-slate-700">Beschrijving <span class="font-normal text-slate-500">(standaardtekst voor projecten en facturen)</span></label><textarea id="description" name="description" rows="4" class="{{ $input }}">{{ old('description', $service->description) }}</textarea></div>
                <div><label for="internal_note" class="mb-1 block text-sm font-medium text-slate-700">Interne toelichting <span class="font-normal text-slate-500">(niet zichtbaar voor de klant)</span></label><textarea id="internal_note" name="internal_note" rows="3" class="{{ $input }}">{{ old('internal_note', $service->internal_note) }}</textarea></div>
            </div>
            <div class="space-y-4">
                <h2 class="text-base font-semibold text-brand-700">Administratie en registratie</h2>
                <x-select name="revenue_group_id" label="Omzetgroep" placeholder="Kies een omzetgroep" :options="$revenueGroups" :selected="$service->revenue_group_id" />
                <x-select name="vat_code" label="Btw-code" placeholder="Kies een btw-code" :options="App\Models\Organization::VAT_CODES" :selected="$service->vat_code" />
                <div class="space-y-2 pt-1 text-sm">
                    @foreach (['allow_hours' => ['Uren mogen worden geboekt', 'Medewerkers kunnen uren op deze dienst registreren.'], 'allow_costs' => ['Inkoop en kosten mogen worden geboekt', 'Inkoopboekingen kunnen aan deze dienst worden gekoppeld.'], 'is_plannable' => ['Inplanbaar in de capaciteitsplanning', 'Medewerkers kunnen op deze dienst worden ingepland.']] as $name => [$label, $hint])
                        <label class="flex items-start gap-2"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked($flag($name)) class="mt-0.5 rounded border-slate-300 text-brand-600"><span>{{ $label }}<span class="block text-xs text-slate-500">{{ $hint }}</span></span></label>
                    @endforeach
                </div>
            </div>
        </section>

        <section data-rows>
            <h2 class="mb-1 text-base font-semibold text-brand-700">Urensoorten</h2>
            <p class="mb-2 text-xs text-slate-500">Worden als urenbudget meegenomen wanneer de dienst aan een project wordt toegevoegd. Een leeg tarief gebruikt het standaardtarief van de urensoort.</p>
            <table class="w-full max-w-3xl text-sm"><thead><tr class="bg-amber-50 text-left text-xs font-semibold text-slate-700"><th class="px-3 py-2">Urensoort</th><th class="px-3 py-2">Uren</th><th class="px-3 py-2">Uurtarief (€)</th></tr></thead>
                <tbody data-rows-body>
                @foreach ($hourRows as $i => $row)
                    <tr><td class="px-1 py-1"><select name="hour_lines[{{ $i }}][hour_type_id]" class="{{ $input }}"><option value="">—</option>@foreach ($hourTypes as $type)<option value="{{ $type->id }}" @selected((string) ($row['hour_type_id'] ?? '') === (string) $type->id)>{{ $type->name }} (€ {{ number_format($type->default_rate, 2, ',', '.') }})</option>@endforeach</select></td>
                        <td class="px-1 py-1"><input type="number" step="0.25" min="0" name="hour_lines[{{ $i }}][hours]" value="{{ $row['hours'] ?? '' }}" class="{{ $input }}"></td>
                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" name="hour_lines[{{ $i }}][rate]" value="{{ $row['rate'] ?? '' }}" placeholder="standaard" class="{{ $input }}"></td></tr>
                @endforeach
                </tbody></table>
            <button type="button" data-add-row class="mt-2 text-sm font-medium text-brand-700 hover:underline">+ Voeg urensoort toe</button>
        </section>

        <section data-rows>
            <h2 class="mb-1 text-base font-semibold text-brand-700">Kostensoorten</h2>
            <p class="mb-2 text-xs text-slate-500">Standaard inkoopprijs en hoeveelheid. Een lege marge gebruikt de standaardmarge van de kostensoort.</p>
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
    </fieldset>
</form>
@if ($editing && $canEdit)
<div class="flex shrink-0 flex-wrap items-center gap-4 px-4 pb-4 text-sm sm:px-5">
    <span class="text-slate-500">Gebruikt in {{ $service->project_services_count }} {{ $service->project_services_count === 1 ? 'projectdienst' : 'projectdiensten' }}. Wijzigingen hier gelden alleen voor nieuwe projectdiensten; bestaande blijven zoals ze zijn.</span>
    <form method="POST" action="{{ route('services.toggle-blocked', $service) }}">@csrf @method('PATCH')<button class="font-medium text-brand-700 hover:underline">{{ $service->is_blocked ? 'Dienst deblokkeren' : 'Dienst blokkeren' }}</button></form>
    <form method="POST" action="{{ route('services.destroy', $service) }}" onsubmit="return confirm('Deze dienst verwijderen?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">Verwijderen</button></form>
</div>
@endif
@endsection

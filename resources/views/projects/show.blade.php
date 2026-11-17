@extends('layouts.app')
@section('title', $project->name)
@section('content')
@php
    $money = fn ($v) => \Illuminate\Support\Number::currency((float) $v, 'EUR', 'nl');
    $num = fn ($v) => number_format((float) $v, 2, ',', '.');
    $canEdit = auth()->user()->can('projects.edit');
    $input = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $btn = 'rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800';
    $th = 'whitespace-nowrap px-4 py-2.5 text-left text-xs font-semibold text-slate-700';
    $active = $project->status->value === 'active';
    $visibleServices = $project->services->filter(fn ($s) => match ($serviceFilter) { 'inactive' => ! $s->is_active, 'all' => true, default => $s->is_active });
    $tabs = ['settings' => 'Projectinstellingen', 'services' => 'Diensten', 'hours' => 'Uren', 'costs' => 'Kosten', 'billing' => 'Facturatie', 'documents' => 'Documenten', 'timeline' => 'Tijdlijn'];

    $hourTypeRows = collect();
    foreach ($project->services->where('is_active', true) as $service) {
        foreach ($service->hourLines as $line) {
            $row = $hourTypeRows->get($line->hour_type_id, ['name' => $line->hourType->name, 'budget' => 0.0, 'amount' => 0.0, 'spent' => 0.0, 'spent_amount' => 0.0]);
            $row['budget'] += $line->hours;
            $row['amount'] += $line->amount();
            $hourTypeRows->put($line->hour_type_id, $row);
        }
    }
    foreach ($project->timeEntries as $entry) {
        $key = $entry->hour_type_id ?? 0;
        $row = $hourTypeRows->get($key, ['name' => $entry->hourType?->name ?? 'Overig', 'budget' => 0.0, 'amount' => 0.0, 'spent' => 0.0, 'spent_amount' => 0.0]);
        $row['spent'] += $entry->hours;
        $row['spent_amount'] += $entry->amount();
        $hourTypeRows->put($key, $row);
    }

    $costTypeRows = collect();
    foreach ($project->services->where('is_active', true) as $service) {
        foreach ($service->costLines as $line) {
            $row = $costTypeRows->get($line->cost_type_id, ['name' => $line->costType->name, 'budget' => 0.0, 'spent' => 0.0]);
            $row['budget'] += $line->purchaseTotal();
            $costTypeRows->put($line->cost_type_id, $row);
        }
    }
    $purchaseLines = $project->purchases->flatMap(fn ($purchase) => $purchase->lines->map(fn ($line) => [$purchase, $line]));
    foreach ($purchaseLines as [$purchase, $line]) {
        $key = $line->cost_type_id ?? 0;
        $row = $costTypeRows->get($key, ['name' => $line->costType?->name ?? 'Overig', 'budget' => 0.0, 'spent' => 0.0]);
        $row['spent'] += $line->total();
        $costTypeRows->put($key, $row);
    }

    $byEmployee = $project->timeEntries->groupBy('employee_id')->map(fn ($entries) => ['name' => $entries->first()->employee->full_name, 'hours' => $entries->sum('hours'), 'amount' => $entries->sum(fn ($e) => $e->amount())])->sortByDesc('hours');
@endphp
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="flex shrink-0 flex-wrap items-start justify-between gap-4">
    <div>
        <a href="{{ route('projects.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Projecten</a>
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-semibold tracking-tight">{{ $project->name }}</h1>
            @if ($project->organization)<a href="{{ route('organizations.show', $project->organization) }}" class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700 hover:bg-slate-300">{{ $project->organization->name }}</a>@else<span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Intern project</span>@endif
            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">{{ $project->status->label() }}</span>
            @if ($project->is_on_hold)<span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">In afwachting</span>@endif
        </div>
        <p class="text-sm text-slate-500">#{{ $project->number }}@if ($project->manager) · Projectleider {{ $project->manager->full_name }}@endif @if ($project->copiedFrom) · Gekopieerd van <a href="{{ route('projects.show', $project->copiedFrom) }}" class="text-brand-700 hover:underline">{{ $project->copiedFrom->name }}</a>@endif</p>
    </div>
    @if ($canEdit)
    <div class="flex flex-wrap gap-2 text-sm">
        <button type="button" data-open-dialog="copy-project" class="rounded-lg px-3.5 py-2 font-medium ring-1 ring-inset ring-slate-300 hover:bg-white">Kopiëren</button>
        @if ($active)
            <button type="button" data-open-dialog="close-project" class="rounded-lg px-3.5 py-2 font-medium text-rose-700 ring-1 ring-inset ring-rose-300 hover:bg-rose-50">Afronden of sluiten</button>
        @else
            <form method="POST" action="{{ route('projects.reopen', $project) }}">@csrf @method('PATCH')<button class="rounded-lg px-3.5 py-2 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-300 hover:bg-emerald-50">Heropenen</button></form>
        @endif
    </div>
    @endif
</div>

@error('close')<div class="shrink-0 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $message }}</div>@enderror
@error('service')<div class="shrink-0 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $message }}</div>@enderror

<div class="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-5">
    <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">Totaal budget</div><div class="text-lg font-semibold">{{ $money($project->totalBudget()) }}</div></div>
    <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">Besteed</div><div class="text-lg font-semibold {{ $project->isOverBudget() ? 'text-rose-600' : '' }}">{{ $money($project->totalSpent()) }}</div></div>
    <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">Restant</div><div class="text-lg font-semibold {{ $project->totalRemaining() < 0 ? 'text-rose-600' : '' }}">{{ $money($project->totalRemaining()) }}</div></div>
    <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="mb-1 text-xs text-slate-500">Budget verbruikt</div><x-progress :value="$project->consumption()" /></div>
    <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="mb-1 text-xs text-slate-500">Voortgang</div><x-progress :value="$project->progress()" /></div>
</div>

<div data-tabs data-initial="{{ $tab ?? 'services' }}" class="flex min-h-0 flex-col lg:flex-1">
    <div class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 text-sm font-medium" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button" data-tab-target="{{ $key }}" class="-mb-px whitespace-nowrap border-b-2 border-transparent px-4 py-2.5 text-slate-500 hover:text-slate-800">{{ $label }}</button>
        @endforeach
    </div>
    <div class="min-h-0 flex-1 pt-4">

    {{-- Projectinstellingen --}}
    <div data-tab-panel="settings" class="lg:h-full lg:overflow-y-auto">
        <form method="POST" action="{{ route('projects.update', $project) }}" class="grid gap-x-10 gap-y-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:grid-cols-2">
            @csrf @method('PUT')
            <fieldset @disabled(! $canEdit) class="space-y-4">
                <h2 class="text-base font-semibold text-brand-700">Algemene projectgegevens</h2>
                <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4"><span class="text-sm font-medium text-slate-700">In afwachting</span><span class="flex gap-4 text-sm"><label class="flex items-center gap-1"><input type="radio" name="is_on_hold" value="0" @checked(! old('is_on_hold', $project->is_on_hold))> Nee</label><label class="flex items-center gap-1"><input type="radio" name="is_on_hold" value="1" @checked(old('is_on_hold', $project->is_on_hold))> Ja</label></span></div>
                <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4"><span class="text-sm font-medium text-slate-700">Begin- en einddatum</span><span class="flex items-center gap-2"><input type="date" name="start_date" value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}" class="{{ $input }}"> <span class="text-xs text-slate-500">t/m</span> <input type="date" name="end_date" value="{{ old('end_date', $project->end_date?->format('Y-m-d')) }}" class="{{ $input }}"></span></div>
                @error('end_date')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4"><span class="text-sm font-medium text-slate-700">Project factureren</span><span class="flex gap-4 text-sm"><label class="flex items-center gap-1"><input type="radio" name="is_invoiceable" value="1" @checked(old('is_invoiceable', $project->is_invoiceable))> Ja</label><label class="flex items-center gap-1"><input type="radio" name="is_invoiceable" value="0" @checked(! old('is_invoiceable', $project->is_invoiceable))> Nee (intern project)</label></span></div>
                <x-select inline name="organization_id" label="Bedrijf" placeholder="— Geen (intern) —" :options="$customers" :selected="$project->organization_id" />
                <x-select inline name="person_id" label="Contactpersoon" placeholder="— Geen —" :options="$contacts" :selected="$project->person_id" />
                <x-field inline name="name" label="Projectnaam" :value="$project->name" required />
                <x-select inline name="manager_id" label="Projectleider" placeholder="— Geen —" :options="$employees->pluck('full_name', 'id')" :selected="$project->manager_id" />
                <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4"><span class="text-sm font-medium text-slate-700">Kilometers registreren</span><span class="flex items-center gap-3 text-sm"><label class="flex items-center gap-1"><input type="checkbox" name="track_kilometers" value="1" @checked(old('track_kilometers', $project->track_kilometers))> Ja</label> <span class="text-xs text-slate-500">Prijs per km (excl. btw) €</span><input type="number" step="0.01" min="0" name="kilometer_price" value="{{ old('kilometer_price', $project->kilometer_price) }}" class="{{ $input }} !w-24"></span></div>
                <div class="grid grid-cols-[8.5rem_1fr] items-start gap-x-4"><label for="remarks" class="pt-2 text-sm font-medium text-slate-700">Notitie</label><textarea id="remarks" name="remarks" rows="4" class="{{ $input }}">{{ old('remarks', $project->remarks) }}</textarea></div>
                <h2 class="pt-2 text-base font-semibold text-brand-700">Extra gegevens</h2>
                <x-field inline name="number" label="Projectnummer" :value="$project->number" required />
            </fieldset>
            <fieldset @disabled(! $canEdit) class="space-y-4">
                <h2 class="text-base font-semibold text-brand-700">Projectleden</h2>
                <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">Alle medewerkers kunnen dit project kiezen. Je kunt een restrictie aanbrengen door teams of medewerkers aan te vinken.</p>
                <div><h3 class="mb-1 text-sm font-medium">Teams</h3><div class="grid gap-1 sm:grid-cols-2">@foreach ($teams as $id => $name)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="team_ids[]" value="{{ $id }}" @checked(in_array($id, old('team_ids', $project->teams->pluck('id')->all()))) class="rounded border-slate-300 text-brand-600"> {{ $name }}</label>@endforeach</div></div>
                <div><h3 class="mb-1 text-sm font-medium">Individuele medewerkers</h3><div class="grid max-h-64 gap-1 overflow-y-auto sm:grid-cols-2">@foreach ($employees as $employee)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="member_ids[]" value="{{ $employee->id }}" @checked(in_array($employee->id, old('member_ids', $project->members->pluck('id')->all()))) class="rounded border-slate-300 text-brand-600"> {{ $employee->full_name }}</label>@endforeach</div></div>
            </fieldset>
            @if ($canEdit)<div class="lg:col-span-2"><button class="{{ $btn }}">Opslaan</button></div>@endif
        </form>
    </div>

    {{-- Diensten --}}
    <div data-tab-panel="services" class="flex min-h-0 flex-col gap-4 lg:h-full">
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
            <div class="flex gap-1 text-sm">@foreach (['active' => 'Actieve diensten', 'inactive' => 'Inactieve diensten', 'all' => 'Alle diensten'] as $key => $label)<a href="{{ route('projects.show', [$project, 'tab' => 'services', 'services' => $key]) }}" class="rounded-full px-4 py-1.5 font-medium {{ $serviceFilter === $key ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>@endforeach</div>
            @if ($canEdit)<a href="{{ route('projects.services.create', $project) }}" class="{{ $btn }}">Voeg dienst toe</a>@endif
        </div>
        <div class="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">Budget uren</div><div class="font-semibold">{{ $num($project->budgetHours()) }} uren</div><div class="text-sm text-slate-500">{{ $money($project->budgetHoursAmount()) }}</div></div>
            <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">Besteed uren</div><div class="font-semibold">{{ $num($project->spentHours()) }} uren</div><div class="text-sm text-slate-500">{{ $money($project->spentHoursAmount()) }}</div></div>
            <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">Restant uren</div><div class="font-semibold {{ $project->budgetHours() - $project->spentHours() < 0 ? 'text-rose-600' : '' }}">{{ $num($project->budgetHours() - $project->spentHours()) }} uren</div><div class="text-sm text-slate-500">{{ $money($project->budgetHoursAmount() - $project->spentHoursAmount()) }}</div></div>
            <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">Budget kosten</div><div class="font-semibold">{{ $money($project->budgetCosts()) }}</div><div class="text-sm text-slate-500">Besteed {{ $money($project->spentCosts()) }}</div></div>
        </div>
        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto">
            @forelse ($visibleServices as $service)
                <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 {{ $service->is_active ? '' : 'opacity-70' }}">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.5fr)_minmax(0,1.2fr)_auto]">
                        <div>
                            <h3 class="font-semibold">{{ $service->name }}</h3>
                            @if ($service->netPrice() !== null)<div class="text-sm text-slate-600">{{ $money($service->netPrice()) }}@if ($service->discount_percentage > 0) <span class="text-xs text-emerald-700">(−{{ rtrim(rtrim(number_format($service->discount_percentage, 2, ',', ''), '0'), ',') }}% korting)</span>@endif</div>@endif
                            <span class="mt-1 inline-block rounded border border-slate-300 px-1.5 py-0.5 text-[11px] text-slate-600">{{ $service->invoice_method->label() }}</span>
                            @if ($service->invoice_from)<div class="mt-1 text-xs text-brand-700">Factureren vanaf {{ $service->invoice_from->format('d-m-Y') }}</div>@endif
                            @if ($canEdit)
                                <form method="POST" action="{{ route('projects.services.progress', [$project, $service]) }}" class="mt-2 flex items-center gap-2 text-xs">@csrf @method('PATCH')<label class="text-slate-500">Voltooid</label><input type="number" name="completion_percentage" min="0" max="100" value="{{ $service->completion_percentage }}" class="w-16 rounded-lg border-0 px-2 py-1 text-xs ring-1 ring-inset ring-slate-300"> %<button class="text-brand-700 hover:underline">Opslaan</button></form>
                            @else<div class="mt-2 text-xs text-slate-500">Voltooid: {{ $service->completion_percentage }}%</div>@endif
                            <x-progress :value="$service->completion_percentage" class="mt-1" />
                        </div>
                        <div>
                            <div class="mb-1 text-xs font-semibold text-slate-500">Uren</div>
                            @if ($service->budgetHours() > 0)
                                <x-progress :value="$service->hoursConsumption()" />
                                <dl class="mt-1 grid grid-cols-[auto_1fr_auto] gap-x-3 text-xs text-slate-600"><dt>Budget</dt><dd class="text-right">{{ $num($service->budgetHours()) }} uren</dd><dd class="text-right">{{ $money($service->budgetHoursAmount()) }}</dd><dt>Besteed</dt><dd class="text-right">{{ $num($service->spentHours()) }} uren</dd><dd class="text-right">{{ $money($service->spentHoursAmount()) }}</dd><dt>Restant</dt><dd class="text-right {{ $service->remainingHours() < 0 ? 'font-semibold text-rose-600' : '' }}">{{ $num($service->remainingHours()) }} uren</dd><dd class="text-right">{{ $money($service->remainingHoursAmount()) }}</dd></dl>
                            @else<span class="text-sm text-slate-400">Geen urenbudget</span>@endif
                        </div>
                        <div>
                            <div class="mb-1 text-xs font-semibold text-slate-500">Kosten</div>
                            @if ($service->budgetCosts() > 0)
                                <x-progress :value="$service->costsConsumption()" />
                                <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 text-xs text-slate-600"><dt>Budget</dt><dd class="text-right">{{ $money($service->budgetCosts()) }}</dd><dt>Besteed</dt><dd class="text-right">{{ $money($service->spentCosts()) }}</dd><dt>Restant</dt><dd class="text-right {{ $service->remainingCosts() < 0 ? 'font-semibold text-rose-600' : '' }}">{{ $money($service->remainingCosts()) }}</dd></dl>
                            @else<span class="text-sm text-slate-400">Geen kostenbudget</span>@endif
                        </div>
                        @if ($canEdit)
                        <div class="flex flex-row gap-2 text-xs lg:flex-col lg:items-end">
                            <a href="{{ route('projects.services.edit', [$project, $service]) }}" class="font-medium text-brand-700 hover:underline">Bewerken</a>
                            <form method="POST" action="{{ route('projects.services.toggle-active', [$project, $service]) }}">@csrf @method('PATCH')<button class="text-slate-600 hover:underline">{{ $service->is_active ? 'Deactiveren' : 'Activeren' }}</button></form>
                            <span class="flex gap-1"><form method="POST" action="{{ route('projects.services.move', [$project, $service]) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="up"><button class="rounded px-1.5 ring-1 ring-slate-300 hover:bg-slate-50" title="Omhoog" aria-label="Omhoog">↑</button></form><form method="POST" action="{{ route('projects.services.move', [$project, $service]) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="down"><button class="rounded px-1.5 ring-1 ring-slate-300 hover:bg-slate-50" title="Omlaag" aria-label="Omlaag">↓</button></form></span>
                            <form method="POST" action="{{ route('projects.services.destroy', [$project, $service]) }}" onsubmit="return confirm('Deze dienst verwijderen?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">Verwijderen</button></form>
                        </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">Geen diensten. Klik op "Voeg dienst toe" om te beginnen.</p>
            @endforelse
        </div>
    </div>

    {{-- Uren --}}
    <div data-tab-panel="hours" class="grid gap-4 lg:h-full lg:grid-cols-3 lg:grid-rows-[auto_1fr]">
        <x-card title="Uren per urensoort" flush class="lg:col-span-2">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Urensoort</th><th class="{{ $th }}">Budget</th><th class="{{ $th }}">Besteed</th><th class="{{ $th }} w-40"></th><th class="{{ $th }}">Restant</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($hourTypeRows as $row)
                    <tr><td class="px-4 py-2.5 font-medium">{{ $row['name'] }}</td><td class="px-4 py-2.5">{{ $num($row['budget']) }} uren<div class="text-xs text-slate-500">{{ $money($row['amount']) }}</div></td><td class="px-4 py-2.5">{{ $num($row['spent']) }} uren<div class="text-xs text-slate-500">{{ $money($row['spent_amount']) }}</div></td><td class="px-4 py-2.5"><x-progress :value="$row['budget'] > 0 ? (int) round($row['spent'] / $row['budget'] * 100) : 0" /></td><td class="px-4 py-2.5 {{ $row['budget'] - $row['spent'] < 0 ? 'font-semibold text-rose-600' : '' }}">{{ $num($row['budget'] - $row['spent']) }} uren<div class="text-xs font-normal text-slate-500">{{ $money($row['amount'] - $row['spent_amount']) }}</div></td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nog geen urenbudget of registraties.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
        <x-card title="Uren registreren" class="lg:row-span-2">
            @if ($active && $registrableEmployees->isNotEmpty())
            <form method="POST" action="{{ route('projects.hours.store', $project) }}" class="space-y-3">
                @csrf
                <x-select name="employee_id" label="Medewerker" :options="$registrableEmployees->pluck('full_name', 'id')" :selected="auth()->user()->employee?->id" />
                <x-select name="project_service_id" label="Dienst" placeholder="— Kies een dienst —" :options="$project->services->where('is_active', true)->where('allow_hours', true)->pluck('name', 'id')" />
                <x-select name="hour_type_id" label="Urensoort" placeholder="— Geen —" :options="$hourTypes" />
                <div class="grid grid-cols-2 gap-3"><x-field name="date" label="Datum" type="date" :value="today()->format('Y-m-d')" required /><x-field name="hours" label="Uren" type="number" step="0.25" min="0.25" max="24" required /></div>
                <x-field name="note" label="Opmerking" />
                <button class="{{ $btn }}">Registreren</button>
            </form>
            @else
                <p class="text-sm text-slate-500">Uren registreren kan alleen op een actief project{{ $registrableEmployees->isEmpty() ? ' en met een gekoppeld medewerkersprofiel' : '' }}.</p>
            @endif
            <h3 class="mb-2 mt-6 text-sm font-semibold">Per medewerker</h3>
            <ul class="divide-y divide-slate-100 text-sm">@forelse ($byEmployee as $row)<li class="flex justify-between py-1.5"><span>{{ $row['name'] }}</span><span class="text-slate-600">{{ $num($row['hours']) }} u · {{ $money($row['amount']) }}</span></li>@empty<li class="py-1.5 text-slate-500">Nog geen uren.</li>@endforelse</ul>
        </x-card>
        <x-card title="Detail uren" flush class="lg:col-span-2">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Datum</th><th class="{{ $th }}">Medewerker</th><th class="{{ $th }}">Dienst</th><th class="{{ $th }}">Urensoort</th><th class="{{ $th }} text-right">Uren</th><th class="{{ $th }} text-right">Bedrag</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($project->timeEntries as $entry)
                    <tr><td class="whitespace-nowrap px-4 py-2">{{ $entry->date->format('d-m-Y') }}<span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium {{ $entry->status->badge() }}">{{ $entry->status->label() }}</span></td><td class="px-4 py-2">{{ $entry->employee->full_name }}@if ($entry->note)<div class="text-xs text-slate-500">{{ $entry->note }}</div>@endif</td><td class="px-4 py-2">{{ $entry->service?->name ?? '—' }}</td><td class="px-4 py-2">{{ $entry->hourType?->name ?? '—' }}</td><td class="px-4 py-2 text-right">{{ $num($entry->hours) }}</td><td class="px-4 py-2 text-right">{{ $money($entry->amount()) }}</td>
                        <td class="px-4 py-2 text-right">@if ($entry->canBeEditedBy(auth()->user()) && ! $entry->isLocked())<form method="POST" action="{{ route('projects.hours.destroy', [$project, $entry]) }}" onsubmit="return confirm('Deze registratie verwijderen?')">@csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Verwijderen</button></form>@endif</td></tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Nog geen uren geregistreerd.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
    </div>

    {{-- Kosten --}}
    <div data-tab-panel="costs" class="grid gap-4 lg:h-full lg:grid-cols-2 lg:grid-rows-2">
        <x-card title="Kosten per kostensoort" flush>
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Kostensoort</th><th class="{{ $th }}">Budget</th><th class="{{ $th }}">Besteed</th><th class="{{ $th }} w-32"></th><th class="{{ $th }}">Restant</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($costTypeRows as $row)
                    <tr><td class="px-4 py-2.5 font-medium">{{ $row['name'] }}</td><td class="px-4 py-2.5">{{ $money($row['budget']) }}</td><td class="px-4 py-2.5">{{ $money($row['spent']) }}</td><td class="px-4 py-2.5"><x-progress :value="$row['budget'] > 0 ? (int) round($row['spent'] / $row['budget'] * 100) : 0" /></td><td class="px-4 py-2.5 {{ $row['budget'] - $row['spent'] < 0 ? 'font-semibold text-rose-600' : '' }}">{{ $money($row['budget'] - $row['spent']) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nog geen kostenbudget of inkoop.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
        <x-card title="Inkoopboekingen" flush class="lg:row-span-2">
            <x-slot:actions>@if ($canEdit && $active)<a href="{{ route('purchases.create', ['project_id' => $project->id]) }}" class="text-sm font-medium text-brand-700 hover:underline">Inkoop registreren</a>@endif</x-slot:actions>
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Datum</th><th class="{{ $th }}">Omschrijving</th><th class="{{ $th }}">Status</th><th class="{{ $th }} text-right">Totaal</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($project->purchases as $purchase)
                    <tr class="align-top"><td class="whitespace-nowrap px-4 py-2">{{ $purchase->date->format('d-m-Y') }}</td><td class="px-4 py-2">{{ $purchase->description }}<div class="text-xs text-slate-500">{{ $purchase->supplier?->name }} @foreach ($purchase->lines as $line)· {{ $line->service?->name ?? 'Geen dienst' }}{{ $line->costType ? ' / '.$line->costType->name : '' }} @endforeach</div></td><td class="px-4 py-2">{{ $purchase->status->label() }}</td><td class="px-4 py-2 text-right">{{ $money($purchase->total()) }}</td>
                        <td class="px-4 py-2 text-right">@if ($canEdit)<form method="POST" action="{{ route('purchases.destroy', $purchase) }}" onsubmit="return confirm('Deze boeking verwijderen?')">@csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Verwijderen</button></form>@endif</td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nog geen inkoopboekingen.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
        <x-card title="Kilometers"><p class="text-sm text-slate-600">{{ $project->track_kilometers ? 'Kilometerregistratie staat aan'.($project->kilometer_price ? ' (€ '.number_format($project->kilometer_price, 2, ',', '.').' per km, excl. btw)' : '').'.' : 'Kilometerregistratie staat uit voor dit project.' }} Kilometers worden geregistreerd in de module Uren.</p></x-card>
    </div>

    {{-- Facturatie --}}
    <div data-tab-panel="billing" class="grid gap-4 lg:h-full lg:grid-cols-3">
        <x-card title="Diensten en factuurmethode" flush class="lg:col-span-2">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Dienst</th><th class="{{ $th }}">Methode</th><th class="{{ $th }} text-right">Prijs</th><th class="{{ $th }}">Factureren vanaf</th><th class="{{ $th }} text-right">Te factureren (indicatie)</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($project->services->where('is_active', true) as $service)
                    @php
                        $indication = $service->invoice_method === \App\Enums\InvoiceMethod::AfterCalculation
                            ? $service->spentHoursAmount()
                            : round(($service->netPrice() ?? 0) * $service->completion_percentage / 100, 2);
                    @endphp
                    <tr><td class="px-4 py-2.5 font-medium">{{ $service->name }}</td><td class="px-4 py-2.5">{{ $service->invoice_method->label() }}</td><td class="px-4 py-2.5 text-right">{{ $service->netPrice() !== null ? $money($service->netPrice()) : '—' }}</td><td class="px-4 py-2.5">{{ $service->invoice_from?->format('d-m-Y') ?? '—' }}</td><td class="px-4 py-2.5 text-right">{{ $project->is_invoiceable ? $money($indication) : '—' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Geen actieve diensten.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
        <x-card title="Facturen"><p class="text-sm text-slate-600">@if ($project->is_invoiceable)Dit project wordt gefactureerd. Facturen verschijnen hier zodra de module Facturen beschikbaar is. De indicatie links gaat uit van het voltooide percentage (vaste prijs) of de bestede uren (nacalculatie).@else Dit is een intern project en wordt niet gefactureerd.@endif</p></x-card>
    </div>

    <x-documents data-tab-panel="documents" class="lg:h-full" :documents="$project->documents" :action="route('projects.documents.store', $project)" :can-manage="$canEdit" />

    <div data-tab-panel="timeline" class="lg:h-full"><x-timeline :notes="$project->notes" :action="route('projects.notes.store', $project)" :can-post="$canEdit" :can-delete="$canEdit" class="mx-auto w-full max-w-3xl lg:h-full" /></div>
    </div>
</div>
</div>

@if ($canEdit)
<dialog id="copy-project" class="m-auto w-full max-w-md rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('projects.copy', $project) }}" class="p-6">
        @csrf
        <h2 class="mb-1 text-lg font-semibold">Project kopiëren</h2>
        <p class="mb-4 text-sm text-slate-600">Diensten, budgetten en projectleden worden meegenomen. Geregistreerde uren en inkoop niet.</p>
        <div class="space-y-3"><x-field name="name" label="Naam van het nieuwe project" :value="$project->name.' (kopie)'" :keep="false" required /><x-field name="number" label="Projectnummer" :keep="false" hint="Leeg = volgend nummer" /></div>
        <div class="mt-5 flex justify-end gap-2"><button type="button" data-close-dialog class="rounded-full px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Annuleren</button><button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">Kopiëren</button></div>
    </form>
</dialog>
<dialog id="close-project" class="m-auto w-full max-w-md rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('projects.close', $project) }}" class="p-6">
        @csrf
        <h2 class="mb-1 text-lg font-semibold">Project afronden of sluiten</h2>
        <p class="mb-4 text-sm text-slate-600">Sluiten kan pas als alle inkoopboekingen verwerkt zijn. Afronden betekent dat het werk klaar is; sluiten betekent dat ook de administratie klaar is. Er kunnen daarna geen uren meer worden geregistreerd.</p>
        <div class="space-y-2 text-sm"><label class="flex items-center gap-2"><input type="radio" name="status" value="completed"> Afronden (werk klaar)</label><label class="flex items-center gap-2"><input type="radio" name="status" value="closed" checked> Sluiten (werk en administratie klaar)</label><label class="mt-3 flex items-center gap-2"><input type="checkbox" name="force" value="1" class="rounded border-slate-300"> Toch sluiten, ook als diensten nog niet 100% voltooid zijn</label></div>
        <div class="mt-5 flex justify-end gap-2"><button type="button" data-close-dialog class="rounded-full px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Annuleren</button><button class="rounded-full bg-rose-600 px-5 py-2 text-sm font-semibold text-white hover:bg-rose-700">Bevestigen</button></div>
    </form>
</dialog>
@endif
@endsection

@extends('layouts.app')
@section('title', 'Projecten')
@section('content')
@php($money = fn ($v) => \Illuminate\Support\Number::currency((float) $v, 'EUR', 'nl'))
<div class="flex flex-col lg:h-full lg:flex-row">
<div class="flex min-w-0 flex-1 flex-col gap-4 p-4 sm:p-5">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
        <x-project-nav :active="$tab" />
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <button type="button" data-toggle="filters" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 lg:hidden">Filters</button>
            <details class="relative">
                <summary class="cursor-pointer list-none rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Opties</summary>
                <div class="absolute right-0 z-20 mt-2 w-60 rounded-lg bg-white p-1 shadow-lg ring-1 ring-slate-200">
                    @can('projects.edit')
                        <a href="{{ route('projects.import') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Projecten importeren</a>
                        <a href="{{ route('projects.import.template') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Importsjabloon downloaden</a>
                    @endcan
                    <a href="{{ route('projects.export', [...request()->query(), 'format' => 'csv']) }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Exporteren als CSV</a>
                    <a href="{{ route('project-settings.index') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Urensoorten, kostensoorten en standaarddiensten</a>
                </div>
            </details>
            <a href="{{ route('projects.export', request()->query()) }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Exporteren naar Excel</a>
            @can('projects.edit')<button type="button" data-open-dialog="new-project" class="rounded-full bg-brand-700 px-4 py-2 font-semibold text-white hover:bg-brand-800">+ Nieuw</button>@endcan
        </div>
    </div>

    <div class="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['Projecten', $stats['projects']], ['Totaal budget', $money($stats['budget'])], ['Besteed', $money($stats['spent'])], ['Boven budget', $stats['over']]] as [$label, $value])
            <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xl font-semibold {{ $label === 'Boven budget' && $value > 0 ? 'text-rose-600' : 'text-ink' }}">{{ $value }}</div><div class="text-xs text-slate-500">{{ $label }}</div></div>
        @endforeach
    </div>

    <div class="flex min-h-[26rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-brand-50 text-left text-xs font-semibold text-slate-700">
                <tr><th class="px-4 py-3">Algemeen ({{ $projects->total() }})</th><th class="px-4 py-3">Uren</th><th class="px-4 py-3">Kosten</th><th class="px-4 py-3">Budget</th><th class="px-4 py-3 text-center">Update</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($projects as $project)
                <tr class="align-top hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="text-xs text-slate-500">{{ $project->organization?->name ?? 'Intern project' }} · #{{ $project->number }}</div>
                        <a href="{{ route('projects.show', $project) }}" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">{{ $project->name }}</a>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            @foreach ($project->services->pluck('invoice_method')->unique() as $method)<span class="rounded border border-slate-300 px-1.5 py-0.5 text-[11px] text-slate-600">{{ $method->label() }}</span>@endforeach
                            @if ($project->is_on_hold)<span class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-800">In afwachting</span>@endif
                            @if ($project->status->value !== 'active')<span class="rounded bg-slate-200 px-1.5 py-0.5 text-[11px] text-slate-700">{{ $project->status->label() }}</span>@endif
                            @if ($project->copied_from_id)<span class="rounded bg-sky-100 px-1.5 py-0.5 text-[11px] text-sky-800">Gekopieerd</span>@endif
                        </div>
                        <div class="mt-1 text-xs text-slate-500">@if ($project->start_date)Begindatum: {{ $project->start_date->format('d M Y') }}@endif @if ($project->manager) · {{ $project->manager->full_name }}@endif</div>
                    </td>
                    <td class="w-64 px-4 py-3">
                        @if ($project->budgetHours() > 0)
                            @php($hoursPct = (int) round($project->spentHours() / $project->budgetHours() * 100))
                            <x-progress :value="$hoursPct" />
                            <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 text-xs text-slate-600"><dt>Budget</dt><dd class="text-right">{{ number_format($project->budgetHours(), 2, ',', '.') }} uren · {{ $money($project->budgetHoursAmount()) }}</dd><dt>Besteed</dt><dd class="text-right">{{ number_format($project->spentHours(), 2, ',', '.') }} uren · {{ $money($project->spentHoursAmount()) }}</dd></dl>
                        @else<span class="text-slate-400">—</span>@endif
                    </td>
                    <td class="w-52 px-4 py-3">
                        @if ($project->budgetCosts() > 0)
                            <x-progress :value="(int) round($project->spentCosts() / $project->budgetCosts() * 100)" />
                            <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 text-xs text-slate-600"><dt>Budget</dt><dd class="text-right">{{ $money($project->budgetCosts()) }}</dd><dt>Besteed</dt><dd class="text-right">{{ $money($project->spentCosts()) }}</dd><dt>Restant</dt><dd class="text-right">{{ $money($project->budgetCosts() - $project->spentCosts()) }}</dd></dl>
                        @else<span class="text-slate-400">—</span>@endif
                    </td>
                    <td class="w-44 px-4 py-3">
                        <div class="font-medium {{ $project->isOverBudget() ? 'text-rose-600' : '' }}">{{ $money($project->totalSpent()) }}</div>
                        <div class="text-xs text-slate-500">van {{ $money($project->totalBudget()) }} · {{ $project->consumption() }}%</div>
                        @if ($project->isOverBudget())<div class="mt-1 text-xs font-medium text-rose-600">Boven budget</div>@endif
                    </td>
                    <td class="w-24 px-4 py-3 text-center"><div class="text-2xl font-semibold {{ $project->daysSinceUpdate() > 14 ? 'text-amber-500' : 'text-emerald-600' }}">{{ $project->daysSinceUpdate() }}</div><div class="text-xs text-slate-500">dagen</div></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">Geen projecten gevonden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-pager :paginator="$projects" />
    </div>
</div>

<aside id="filters" class="hidden w-full shrink-0 overflow-y-auto bg-white px-5 py-5 ring-1 ring-slate-200 lg:block lg:w-72">
    <form method="GET" data-autosubmit>
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek op naam, nummer, klant…" class="mb-4 block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600">
        <label class="mb-1 block text-sm font-semibold text-slate-900">Sorteren op</label>
        <select name="sort" class="mb-5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300">@foreach ($sorts as $value => $label)<option value="{{ $value }}" @selected(request('sort', 'updated_at') === $value)>{{ $label }}</option>@endforeach</select>
        <x-filter-group title="Factuurmethode" name="invoice_method" :options="$methods" />
        <x-filter-group title="In afwachting" name="on_hold" type="radio" default="" :options="['' => 'Alle', '1' => 'Ja', '0' => 'Nee']" />
        <x-filter-group title="Klant" name="organization_id" :options="$customers" />
        <x-filter-group title="Projectleider" name="manager_id" :options="$managers" />
        <x-filter-group title="Medewerker" name="employee_id" :options="$employees->pluck('full_name', 'id')" />
        <x-filter-group title="Project factureren" name="invoiceable" type="radio" default="" :options="['' => 'Alle', '1' => 'Ja', '0' => 'Nee (intern project)']" />
        <x-filter-group title="Project is gekopieerd" name="copied" type="radio" default="" :options="['' => 'Alle', '1' => 'Ja', '0' => 'Nee']" />
        <x-filter-group title="Standaarddienst" name="default_service_id" :options="$defaultServices" />
        <a href="{{ route('projects.index', ['tab' => $tab]) }}" class="text-sm text-brand-700 hover:underline">Filters wissen</a>
    </form>
</aside>
</div>

@can('projects.edit')
<dialog id="new-project" class="m-auto w-full max-w-lg rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('projects.store') }}" class="p-6">
        @csrf
        <h2 class="mb-4 text-lg font-semibold">Project toevoegen</h2>
        <div class="space-y-3">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_invoiceable" value="1" checked data-reveal="np-company" class="rounded border-slate-300 text-brand-600"> Project factureren (uitvinken voor een intern project)</label>
            <div id="np-company"><x-select inline name="organization_id" label="Bedrijf" placeholder="— Kies een bedrijf —" :options="$customers" /></div>
            <x-field inline name="name" label="Projectnaam" required />
            <x-select inline name="manager_id" label="Projectleider" placeholder="— Geen —" :options="$employees->pluck('full_name', 'id')" />
            <x-field inline name="number" label="Projectnummer" :hint="'Laatst toegevoegde: '.($nextNumber - 1).'. Leeg = '.$nextNumber" />
        </div>
        <div class="mt-5 flex justify-end gap-2"><button type="button" data-close-dialog class="rounded-full px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Annuleren</button><button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">Toevoegen</button></div>
    </form>
</dialog>
@endcan
@endsection

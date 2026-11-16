@extends('layouts.app')
@section('title', 'Collectief verlof')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<x-hr-nav />
<h1 class="shrink-0 text-2xl font-semibold tracking-tight">Collectief verlof</h1>
@if (session('collective_skipped'))
    <div class="shrink-0 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200"><p class="font-medium">Overgeslagen:</p><ul class="list-disc pl-5">@foreach (session('collective_skipped') as $line)<li>{{ $line }}</li>@endforeach</ul></div>
@endif
<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-3">
    <x-card title="Eerder geboekt" flush class="lg:col-span-2">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="px-4 py-2.5">Naam</th><th class="px-4 py-2.5">Periode</th><th class="px-4 py-2.5">Soort</th><th class="px-4 py-2.5">Medewerkers</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($collectives as $collective)
                <tr><td class="px-4 py-2.5 font-medium">{{ $collective->name }}</td><td class="px-4 py-2.5">{{ $collective->start_date->format('d-m-Y') }} – {{ $collective->end_date->format('d-m-Y') }}</td><td class="px-4 py-2.5">{{ $collective->leaveType->name }}</td><td class="px-4 py-2.5">{{ $collective->requests_count }}</td></tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Nog geen collectief verlof geboekt.</td></tr>
            @endforelse
            </tbody>
        </table>
        <x-slot:footer><x-pager :paginator="$collectives" /></x-slot:footer>
    </x-card>
    <x-card title="Collectief verlof boeken">
        <form method="POST" action="{{ route('collective-leave.store') }}" class="space-y-3">
            @csrf
            <x-field name="name" label="Naam" placeholder="Bijv. Kerstsluiting" required />
            <x-select name="leave_type_id" label="Soort verlof" :options="$leaveTypes" />
            <div class="grid grid-cols-2 gap-3"><x-field name="start_date" label="Van" type="date" required /><x-field name="end_date" label="Tot en met" type="date" required /></div>
            <fieldset>
                <legend class="mb-1 text-sm font-medium text-slate-700">Voor wie?</legend>
                @foreach (['all' => 'Alle medewerkers in dienst', 'teams' => 'Geselecteerde teams', 'employees' => 'Geselecteerde medewerkers'] as $value => $label)
                    <label class="flex items-center gap-2 text-sm"><input type="radio" name="scope" value="{{ $value }}" @checked(old('scope', 'all') === $value) class="border-slate-300 text-brand-600"> {{ $label }}</label>
                @endforeach
                @error('scope')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </fieldset>
            <details class="rounded-lg ring-1 ring-slate-200"><summary class="cursor-pointer px-3 py-2 text-sm font-medium">Teams</summary>
                <div class="max-h-32 space-y-1 overflow-y-auto px-3 pb-2">@foreach ($teams as $id => $name)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="team_ids[]" value="{{ $id }}" class="rounded border-slate-300 text-brand-600"> {{ $name }}</label>@endforeach</div></details>
            <details class="rounded-lg ring-1 ring-slate-200"><summary class="cursor-pointer px-3 py-2 text-sm font-medium">Medewerkers</summary>
                <div class="max-h-40 space-y-1 overflow-y-auto px-3 pb-2">@foreach ($employees as $employee)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" class="rounded border-slate-300 text-brand-600"> {{ $employee->full_name }}</label>@endforeach</div></details>
            <p class="text-xs text-slate-500">Uren worden per medewerker uit het rooster berekend, direct goedgekeurd en van het saldo afgeschreven (bij soorten met saldo).</p>
            <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Verlof boeken</button>
        </form>
    </x-card>
</div>
</div>
@endsection

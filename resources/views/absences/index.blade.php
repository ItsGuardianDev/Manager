@extends('layouts.app')
@section('title', 'Verzuim')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<x-hr-nav />
<div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold tracking-tight">Ziekte en verzuim</h1>
    <div class="flex gap-1 text-sm">
        @foreach (['ongoing' => 'Lopend', 'closed' => 'Afgerond', 'all' => 'Alle'] as $key => $label)
            <a href="{{ route('absences.index', ['status' => $key]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $status === $key ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-3">
    <div class="flex min-h-[20rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2 lg:min-h-0">
        <div class="min-h-0 flex-1 overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="px-4 py-3">Medewerker</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Soort</th><th class="px-4 py-3">%</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($absences as $absence)
                    <tr class="align-top">
                        <td class="px-4 py-2.5"><a href="{{ route('employees.show', [$absence->employee, 'tab' => 'absence']) }}" class="font-medium hover:text-brand-700 hover:underline">{{ $absence->employee->full_name }}</a><div class="text-xs text-slate-500">{{ $absence->employee->teams->pluck('name')->implode(', ') }}</div></td>
                        <td class="whitespace-nowrap px-4 py-2.5">{{ $absence->start_date->format('d-m-Y') }} – {{ $absence->end_date?->format('d-m-Y') ?? 'lopend' }}</td>
                        <td class="px-4 py-2.5">{{ $absence->type->label() }}</td>
                        <td class="px-4 py-2.5">{{ $absence->percentage }}%</td>
                        <td class="px-4 py-2.5 text-right">
                            @if ($absence->isOngoing())
                            <form method="POST" action="{{ route('absences.update', $absence) }}" class="flex items-center justify-end gap-2">@csrf @method('PUT')
                                <input type="date" name="end_date" required value="{{ today()->format('Y-m-d') }}" class="rounded-lg border-0 px-2 py-1 text-xs shadow-sm ring-1 ring-inset ring-slate-300">
                                <button class="whitespace-nowrap text-xs font-medium text-brand-700 hover:underline">Hersteld</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">Geen verzuim gevonden.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$absences" />
    </div>
    <x-card title="Ziek melden">
        <form method="POST" action="{{ route('absences.store') }}" class="space-y-3">
            @csrf
            <x-select name="employee_id" label="Medewerker" placeholder="— Kies —" :options="$employees->pluck('full_name', 'id')" />
            <x-select name="type" label="Soort" :options="$absenceTypes" />
            <div class="grid grid-cols-2 gap-3"><x-field name="start_date" label="Eerste ziektedag" type="date" :value="today()->format('Y-m-d')" required /><x-field name="end_date" label="Hersteld op" type="date" /></div>
            <x-field name="percentage" label="Arbeidsongeschiktheid (%)" type="number" min="1" max="100" value="100" required />
            <x-field name="note" label="Opmerking" />
            <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Registreren</button>
        </form>
    </x-card>
</div>
</div>
@endsection

@extends('layouts.app')
@section('title', $mode === 'approvals' ? 'Uren goedkeuren' : 'Uren')
@section('content')
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') ?: '0';
    $field = 'rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $user = auth()->user();
    $route = $mode === 'approvals' ? 'hours.approvals' : 'hours.index';
@endphp
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
        <x-hours-nav :active="$mode === 'approvals' ? 'approvals' : 'overview'" />
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ route('hours.report.export', request()->query()) }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Exporteren naar Excel</a>
            @if ($user->can('hours.register') || $user->can('hours.manage'))<button type="button" data-entry-open data-date="{{ today()->format('Y-m-d') }}" class="rounded-full bg-brand-700 px-4 py-2 font-semibold text-white hover:bg-brand-800">+ Uren registreren</button>@endif
        </div>
    </div>

    <form method="GET" action="{{ route($route) }}" data-autosubmit class="flex shrink-0 flex-wrap items-end gap-2 text-sm">
        @if ($canSeeOthers)<select name="employee_id" class="{{ $field }}"><option value="">Alle medewerkers</option>@foreach ($employeeOptions as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select>@endif
        <select name="project_id" class="{{ $field }}"><option value="">Alle projecten</option>@foreach ($projectOptions as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select>
        <select name="hour_type_id" class="{{ $field }}"><option value="">Alle urensoorten</option>@foreach ($hourTypeOptions as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['hour_type_id'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select>
        <select name="status" class="{{ $field }}"><option value="">Alle statussen</option>@foreach ($statusOptions as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $field }}" title="Vanaf"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $field }}" title="Tot en met">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Zoek in omschrijving…" class="{{ $field }}">
        <button class="rounded-lg bg-white px-3 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Filteren</button>
        @if (collect($filters)->except($mode === 'approvals' ? 'status' : '')->filter()->isNotEmpty() || ($mode === 'approvals' && request()->has('status')))<a href="{{ route($route) }}" class="py-2 text-slate-500 hover:text-slate-800">Wissen</a>@endif
    </form>

    <div class="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['Totaal uren', $num($totalHours), false], ['Concept', $num(($stats['draft']->hours ?? 0) + ($stats['rejected']->hours ?? 0)), false], ['Ter goedkeuring', $num($stats['submitted']->hours ?? 0), ($stats['submitted']->hours ?? 0) > 0], ['Goedgekeurd', $num($stats['approved']->hours ?? 0), false]] as [$label, $value, $highlight])
            <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xl font-semibold {{ $highlight ? 'text-amber-600' : 'text-ink' }}">{{ $value }}</div><div class="text-xs text-slate-500">{{ $label }}</div></div>
        @endforeach
    </div>

    @if (session('status'))<div class="shrink-0 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>@endif
    @if ($errors->any() && ! old('_dialog'))<div class="shrink-0 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('hours.bulk') }}" class="flex min-h-[22rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
        @csrf
        <div class="flex shrink-0 flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-2.5 text-sm">
            <span class="text-slate-500">Met selectie:</span>
            @if ($mode === 'overview')<button name="action" value="submit" class="rounded-full bg-white px-3 py-1 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Indienen</button>@endif
            @if ($canSeeOthers || $mode === 'approvals')
                <button name="action" value="approve" class="rounded-full bg-emerald-600 px-3 py-1 font-medium text-white hover:bg-emerald-700">Goedkeuren</button>
                <input name="reason" placeholder="Reden bij afkeuren" class="w-48 rounded-lg border-0 px-3 py-1 text-sm shadow-sm ring-1 ring-inset ring-slate-300"><button name="action" value="reject" class="rounded-full bg-white px-3 py-1 font-medium text-rose-700 ring-1 ring-inset ring-rose-300 hover:bg-rose-50">Afkeuren</button>
            @endif
            @can('hours.manage')<button name="action" value="invoice" class="rounded-full bg-white px-3 py-1 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50" title="Goedgekeurde uren vergrendelen voor facturatie">Verwerkt voor facturatie</button>@endcan
        </div>
        <div class="min-h-0 flex-1 overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="w-10 px-4 py-3"><input type="checkbox" data-check-all class="rounded border-slate-300 text-brand-600" aria-label="Alles selecteren"></th><th class="px-3 py-3">Datum</th><th class="px-3 py-3">Medewerker</th><th class="px-3 py-3">Project</th><th class="px-3 py-3">Dienst</th><th class="px-3 py-3">Urensoort</th><th class="px-3 py-3">Tijd</th><th class="px-3 py-3 text-right">Uren</th><th class="px-3 py-3">Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($entries as $entry)
                    @php
                        $editable = $entry->canBeEditedBy($user);
                        $payload = $entry->dialogPayload($user);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5"><input type="checkbox" name="ids[]" value="{{ $entry->id }}" class="rounded border-slate-300 text-brand-600" data-row-check></td>
                        <td class="whitespace-nowrap px-3 py-2.5">{{ $entry->date->format('d-m-Y') }}</td>
                        <td class="px-3 py-2.5">{{ $entry->employee->full_name }}</td>
                        <td class="px-3 py-2.5"><a href="{{ route('projects.show', [$entry->project, 'tab' => 'hours']) }}" class="text-brand-700 hover:underline">{{ $entry->project->name }}</a>@if ($entry->note)<div class="max-w-xs truncate text-xs text-slate-500">{{ $entry->note }}</div>@endif</td>
                        <td class="px-3 py-2.5">{{ $entry->service?->name ?? '—' }}</td>
                        <td class="px-3 py-2.5">{{ $entry->hourType?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-slate-600">{{ $entry->start_time ? $entry->start_time.'–'.($entry->end_time ?? '') : '—' }}</td>
                        <td class="px-3 py-2.5 text-right font-medium">{{ $num($entry->hours) }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $entry->status->badge() }}" @if ($entry->reject_reason) title="{{ $entry->reject_reason }}" @endif>{{ $entry->status->label() }}</span>@if ($entry->isLocked())<span class="ml-1 text-xs text-slate-500" title="Verwerkt voor facturatie op {{ $entry->invoiced_at->format('d-m-Y') }}">🔒</span>@endif</td>
                        <td class="px-3 py-2.5 text-right"><button type="button" data-entry-open data-entry='@json($payload)' class="text-xs font-medium text-brand-700 hover:underline">{{ $editable ? 'Bewerken' : 'Bekijken' }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-12 text-center text-slate-500">{{ $mode === 'approvals' ? 'Geen uren om goed te keuren.' : 'Geen uren gevonden. Klik op "Uren registreren" om te beginnen.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$entries" />
    </form>
</div>
@include('hours._entry-dialog')
@endsection

@extends('layouts.app')
@section('title', 'Weekstaat')
@section('content')
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') ?: '0';
    $field = 'rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $cell = 'w-16 rounded-md border-0 px-2 py-1 text-right text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 disabled:bg-slate-50 disabled:text-slate-500';
    $user = auth()->user();
    $params = fn (array $extra = []) => ['employee_id' => $canPickEmployee ? $employee->id : null, ...$extra];
    $weekTotal = $rows->sum(fn ($row) => $row['cells']->flatten()->sum('hours'));
    $scheduledTotal = $scheduled->sum();
    $input = 'block w-full rounded-lg border-0 bg-white px-2 py-1.5 text-xs shadow-sm ring-1 ring-inset ring-slate-300';
@endphp
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
        <x-hours-nav active="week" />
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @if ($canPickEmployee)
                <form method="GET"><input type="hidden" name="date" value="{{ $monday->format('Y-m-d') }}"><select name="employee_id" onchange="this.form.submit()" class="{{ $field }}">@foreach ($employeeOptions as $id => $name)<option value="{{ $id }}" @selected($id === $employee->id)>{{ $name }}</option>@endforeach</select></form>
            @endif
            <a href="{{ route('hours.week', $params(['date' => $monday->copy()->subWeek()->format('Y-m-d')])) }}" class="rounded-full bg-white px-3 py-2 ring-1 ring-inset ring-slate-300 hover:bg-slate-50" aria-label="Vorige week">←</a>
            <span class="min-w-48 text-center font-medium">Week {{ $monday->isoWeek() }} · {{ $monday->translatedFormat('j M') }} – {{ $monday->copy()->addDays(6)->translatedFormat('j M Y') }}</span>
            <a href="{{ route('hours.week', $params(['date' => $monday->copy()->addWeek()->format('Y-m-d')])) }}" class="rounded-full bg-white px-3 py-2 ring-1 ring-inset ring-slate-300 hover:bg-slate-50" aria-label="Volgende week">→</a>
            <a href="{{ route('hours.week', $params()) }}" class="rounded-full bg-white px-4 py-2 font-medium ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Deze week</a>
        </div>
    </div>

    @if (session('status'))<div class="shrink-0 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="shrink-0 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $errors->first() }}</div>@endif

    <div class="flex min-h-[24rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1" data-timesheet>
        <form method="POST" action="{{ route('hours.week.save') }}" id="week-form" class="flex min-h-0 flex-1 flex-col">
            @csrf
            <input type="hidden" name="date" value="{{ $monday->format('Y-m-d') }}"><input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-brand-50 text-xs font-semibold text-slate-700">
                        <tr><th class="min-w-64 px-4 py-3 text-left">Project · dienst · urensoort</th>@foreach ($days as $day)<th class="px-2 py-3 text-center {{ $day->isToday() ? 'text-brand-700' : '' }} {{ $day->isWeekend() ? 'text-slate-400' : '' }}">{{ $day->translatedFormat('D') }}<div class="font-normal">{{ $day->format('d-m') }}</div></th>@endforeach<th class="px-3 py-3 text-right">Totaal</th><th class="px-3 py-3 text-right">Budget restant</th></tr>
                    </thead>
                    <tbody data-week-body class="divide-y divide-slate-100">
                    @foreach ($rows as $i => $row)
                        @php
                            $first = $row['first'];
                        @endphp
                        <tr data-week-row>
                            <td class="px-4 py-2"><input type="hidden" name="rows[{{ $i }}][project_id]" value="{{ $first->project_id }}"><input type="hidden" name="rows[{{ $i }}][project_service_id]" value="{{ $first->project_service_id }}"><input type="hidden" name="rows[{{ $i }}][hour_type_id]" value="{{ $first->hour_type_id }}">
                                <a href="{{ route('projects.show', [$first->project, 'tab' => 'hours']) }}" class="font-medium text-brand-700 hover:underline">{{ $first->project->name }}</a><div class="text-xs text-slate-500">{{ $first->service?->name }}{{ $first->hourType ? ' · '.$first->hourType->name : '' }}</div></td>
                            @foreach ($days as $day)
                                @php
                                    $key = $day->format('Y-m-d');
                                    $entries = $row['cells'][$key] ?? collect();
                                    $single = $entries->count() === 1 ? $entries->first() : null;
                                    $sum = $entries->sum('hours');
                                    $open = ! $entries->count() || ($single && $single->canBeEditedBy($user) && ! $single->isLocked());
                                @endphp
                                <td class="px-1 py-1.5 text-center">
                                    @if ($open)<input type="number" step="0.25" min="0" max="24" name="rows[{{ $i }}][hours][{{ $key }}]" value="{{ $sum ?: '' }}" data-cell="{{ $key }}" class="{{ $cell }}">
                                    @else<span data-cell="{{ $key }}" data-value="{{ $sum }}" class="inline-block w-16 rounded-md px-2 py-1 text-right {{ $entries->first()?->status->badge() }}" title="{{ $entries->count() > 1 ? 'Meerdere registraties: bewerk ze in de agenda of het overzicht' : $entries->first()->status->label() }}">{{ $num($sum) }}</span>@endif
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-medium">{{ $num($row['cells']->flatten()->sum('hours')) }}</td>
                            <td class="px-3 py-2 text-right text-xs {{ ($first->service?->remainingHours() ?? 0) < 0 ? 'font-semibold text-rose-600' : 'text-slate-500' }}">@if ($first->service && $first->service->budgetHours() > 0){{ $num($first->service->remainingHours()) }} u @else — @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-white text-sm border-t border-slate-200">
                        <tr class="font-semibold"><td class="px-4 py-2">Totaal geregistreerd</td>@foreach ($days as $day)<td class="px-1 py-2 text-center" data-day-total="{{ $day->format('Y-m-d') }}">{{ $num($rows->sum(fn ($row) => ($row['cells'][$day->format('Y-m-d')] ?? collect())->sum('hours'))) }}</td>@endforeach<td class="px-3 py-2 text-right" data-week-total>{{ $num($weekTotal) }}</td><td></td></tr>
                        <tr class="text-slate-500"><td class="px-4 py-1.5">Rooster</td>@foreach ($days as $day)<td class="px-1 py-1.5 text-center">{{ $num($scheduled[$day->format('Y-m-d')]) }}</td>@endforeach<td class="px-3 py-1.5 text-right">{{ $num($scheduledTotal) }}</td><td></td></tr>
                        <tr class="text-slate-500"><td class="px-4 py-1.5">Verschil met rooster</td>@foreach ($days as $day)@php
                            $diff = ($rows->sum(fn ($row) => ($row['cells'][$day->format('Y-m-d')] ?? collect())->sum('hours'))) - $scheduled[$day->format('Y-m-d')];
                        @endphp<td class="px-1 py-1.5 text-center {{ $diff < 0 ? 'text-rose-600' : ($diff > 0 ? 'text-emerald-600' : '') }}" data-day-diff="{{ $day->format('Y-m-d') }}" data-scheduled="{{ $scheduled[$day->format('Y-m-d')] }}">{{ $diff == 0 ? '0' : ($diff > 0 ? '+' : '').$num($diff) }}</td>@endforeach<td></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3 border-t border-slate-100 px-4 py-3 text-sm">
                <button type="button" data-week-add class="font-medium text-brand-700 hover:underline">+ Rij toevoegen</button>
                <span class="text-slate-500">Tijd-voor-tijd saldo: <span class="font-medium text-slate-700">{{ $num($balance) }} u</span></span>
                <button class="ml-auto rounded-full bg-brand-700 px-5 py-2 font-semibold text-white hover:bg-brand-800">Opslaan</button>
            </div>
        </form>
        <template data-week-template>
            <tr data-week-row data-service-scope>
                <td class="space-y-1 px-4 py-2"><select name="rows[__i__][project_id]" data-project-select class="{{ $input }}"><option value="">— Project —</option>@foreach ($projects as $project)<option value="{{ $project->id }}">{{ $project->number }} · {{ $project->name }}</option>@endforeach</select>
                    <select name="rows[__i__][project_service_id]" data-service-select class="{{ $input }}"><option value="">— Dienst —</option>@foreach ($services as $service)<option value="{{ $service->id }}" data-project="{{ $service->project_id }}" data-budget="{{ (float) $service->budget_hours }}" data-spent="{{ (float) $service->spent_hours }}" hidden disabled>{{ $service->name }}</option>@endforeach</select>
                    <select name="rows[__i__][hour_type_id]" class="{{ $input }}"><option value="">— Urensoort —</option>@foreach ($hourTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select><p data-budget-hint class="text-xs text-slate-500"></p></td>
                @foreach ($days as $day)<td class="px-1 py-1.5 text-center"><input type="number" step="0.25" min="0" max="24" name="rows[__i__][hours][{{ $day->format('Y-m-d') }}]" data-cell="{{ $day->format('Y-m-d') }}" class="{{ $cell }}"></td>@endforeach
                <td></td><td></td>
            </tr>
        </template>
        <div class="flex shrink-0 flex-wrap items-center gap-4 border-t border-slate-100 px-4 py-3 text-sm">
            <form method="POST" action="{{ route('hours.week.submit') }}">@csrf<input type="hidden" name="date" value="{{ $monday->format('Y-m-d') }}"><input type="hidden" name="employee_id" value="{{ $employee->id }}"><button @disabled(! $canSubmit) class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Week indienen ter goedkeuring</button></form>
            <form method="POST" action="{{ route('hours.week.copy') }}" class="flex items-center gap-2">@csrf<input type="hidden" name="date" value="{{ $monday->format('Y-m-d') }}"><input type="hidden" name="employee_id" value="{{ $employee->id }}"><label class="text-slate-500" for="copy-target">Kopieer deze week naar week van</label><input id="copy-target" type="date" name="target" value="{{ $monday->copy()->addWeek()->format('Y-m-d') }}" class="{{ $field }}"><button class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Kopiëren</button></form>
        </div>
    </div>
</div>
@include('hours._entry-dialog')
@endsection

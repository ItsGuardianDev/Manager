@extends('layouts.app')
@section('title', 'Agenda')
@section('content')
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') ?: '0';
    $field = 'rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $user = auth()->user();
    $step = $view === 'day' ? 1 : 7;
    $params = fn (array $extra = []) => ['employee_id' => $canPickEmployee ? $employee->id : null, 'view' => $view, ...$extra];
    $startHour = 6;
    $endHour = 22;
    $rem = 3;
    $minutes = fn (?string $time) => $time ? ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2) : null;
    $tones = ['approved' => 'bg-emerald-50 text-emerald-900 ring-emerald-200', 'submitted' => 'bg-amber-50 text-amber-900 ring-amber-200', 'rejected' => 'bg-rose-50 text-rose-900 ring-rose-200', 'draft' => 'bg-brand-50 text-brand-900 ring-brand-200'];
@endphp
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
        <x-hours-nav active="calendar" />
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @if ($canPickEmployee)
                <form method="GET"><input type="hidden" name="date" value="{{ $anchor->format('Y-m-d') }}"><input type="hidden" name="view" value="{{ $view }}"><select name="employee_id" onchange="this.form.submit()" class="{{ $field }}">@foreach ($employeeOptions as $id => $name)<option value="{{ $id }}" @selected($id === $employee->id)>{{ $name }}</option>@endforeach</select></form>
            @endif
            <div class="flex overflow-hidden rounded-full ring-1 ring-inset ring-slate-300">@foreach (['day' => 'Dag', 'week' => 'Week'] as $key => $label)<a href="{{ route('hours.calendar', $params(['view' => $key, 'date' => $anchor->format('Y-m-d')])) }}" class="px-4 py-2 font-medium {{ $view === $key ? 'bg-ink text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">{{ $label }}</a>@endforeach</div>
            <a href="{{ route('hours.calendar', $params(['date' => $anchor->copy()->subDays($step)->format('Y-m-d')])) }}" class="rounded-full bg-white px-3 py-2 ring-1 ring-inset ring-slate-300 hover:bg-slate-50" aria-label="Vorige">←</a>
            <span class="min-w-44 text-center font-medium">{{ $view === 'day' ? $anchor->translatedFormat('l j F Y') : 'Week '.$anchor->isoWeek().' · '.$days->first()->translatedFormat('j M').' – '.$days->last()->translatedFormat('j M') }}</span>
            <a href="{{ route('hours.calendar', $params(['date' => $anchor->copy()->addDays($step)->format('Y-m-d')])) }}" class="rounded-full bg-white px-3 py-2 ring-1 ring-inset ring-slate-300 hover:bg-slate-50" aria-label="Volgende">→</a>
            <a href="{{ route('hours.calendar', $params()) }}" class="rounded-full bg-white px-4 py-2 font-medium ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Vandaag</a>
        </div>
    </div>

    @if (session('status'))<div class="shrink-0 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>@endif
    @if ($errors->any() && ! old('_dialog'))<div class="shrink-0 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $errors->first() }}</div>@endif

    <div class="flex min-h-[28rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
        <div class="min-h-0 flex-1 overflow-auto">
            <div class="grid min-w-[44rem]" style="grid-template-columns: 3.5rem repeat({{ $days->count() }}, minmax(0, 1fr));">
                <div class="sticky top-0 z-20 border-b border-slate-200 bg-brand-50"></div>
                @foreach ($days as $day)
                    @php
                        $key = $day->format('Y-m-d');
                        $total = ($entries[$key] ?? collect())->sum('hours');
                    @endphp
                    <div class="sticky top-0 z-20 border-b border-l border-slate-200 bg-brand-50 px-2 py-2 text-center">
                        <div class="text-xs font-semibold {{ $day->isToday() ? 'text-brand-700' : 'text-slate-700' }}">{{ $day->translatedFormat('D j M') }}</div>
                        <div class="text-xs {{ $total < $scheduled[$key] ? 'text-slate-500' : 'text-emerald-700' }}">{{ $num($total) }} / {{ $num($scheduled[$key]) }} u</div>
                        <button type="button" data-entry-open data-date="{{ $key }}" class="mt-1 text-xs font-medium text-brand-700 hover:underline">+ Registreren</button>
                    </div>
                @endforeach

                <div class="relative" style="height: {{ ($endHour - $startHour) * $rem }}rem">
                    @for ($hour = $startHour; $hour < $endHour; $hour++)<div class="absolute right-1 -translate-y-2 text-[10px] text-slate-400" style="top: {{ ($hour - $startHour) * $rem }}rem">{{ sprintf('%02d:00', $hour) }}</div>@endfor
                </div>
                @foreach ($days as $day)
                    @php
                        $dayEntries = $entries[$day->format('Y-m-d')] ?? collect();
                    @endphp
                    <div class="relative border-l border-slate-200 {{ $day->isWeekend() ? 'bg-slate-50/60' : '' }}" style="height: {{ ($endHour - $startHour) * $rem }}rem; background-image: repeating-linear-gradient(to bottom, transparent 0, transparent calc({{ $rem }}rem - 1px), rgb(226 232 240) calc({{ $rem }}rem - 1px), rgb(226 232 240) {{ $rem }}rem);">
                        @foreach ($dayEntries as $entry)
                            @php
                                $start = $minutes($entry->start_time);
                                $end = $minutes($entry->end_time) ?? ($start !== null ? $start + (int) round($entry->hours * 60) : null);
                                $timed = $start !== null;
                                $top = $timed ? max(0, ($start - $startHour * 60) / 60 * $rem) : 0;
                                $height = $timed ? max(1.25, (min($end, $endHour * 60) - max($start, $startHour * 60)) / 60 * $rem) : 1.5;
                            @endphp
                            <button type="button" data-entry-open data-entry='@json($entry->dialogPayload($user))' title="{{ $entry->project->name }} · {{ $entry->service?->name }}"
                                class="{{ $timed ? 'absolute inset-x-1 overflow-hidden' : 'mx-1 mt-1 block w-[calc(100%-0.5rem)]' }} rounded-lg px-2 py-1 text-left text-xs ring-1 ring-inset {{ $tones[$entry->status->value] }}"
                                @if ($timed) style="top: {{ $top }}rem; height: {{ $height }}rem" @endif>
                                <div class="truncate font-semibold">{{ $entry->project->name }}</div>
                                <div class="truncate">{{ $entry->service?->name }} · {{ $num($entry->hours) }} u{{ $entry->start_time ? ' · '.$entry->start_time.'–'.$entry->end_time : '' }}</div>
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <p class="shrink-0 border-t border-slate-100 px-4 py-2 text-xs text-slate-500">Klik op een registratie om te bewerken, te dupliceren, te splitsen of te verplaatsen. Uren zonder tijd staan bovenaan de dag.</p>
    </div>
</div>
@include('hours._entry-dialog')
@endsection

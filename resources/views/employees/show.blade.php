@extends('layouts.app')
@section('title', $employee->full_name)
@section('content')
@php
    $me = auth()->user();
    $canPrivate = $me->can('viewPrivate', $employee);
    $canContracts = $me->can('viewContracts', $employee);
    $canLeave = $me->can('viewLeave', $employee);
    $canAbsence = $me->can('viewAbsence', $employee);
    $canFile = $me->can('viewDocuments', $employee);
    $canTimeline = $me->can('viewNotes', $employee);
    $canAccess = $me->can('access.manage');
    $fmt = fn ($date) => $date?->format('d-m-Y') ?? '—';
    $num = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $row = fn ($label, $value) => $value ? '<div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">'.e($label).'</dt><dd class="text-right font-medium">'.e($value).'</dd></div>' : '';
    $tabs = array_filter([
        'overview' => 'Overzicht',
        'contracts' => $canContracts ? 'Contracten' : null,
        'schedule' => $canContracts ? 'Rooster' : null,
        'leave' => $canLeave ? 'Verlof' : null,
        'absence' => $canAbsence ? 'Verzuim' : null,
        'file' => $canFile ? 'Personeelsdossier' : null,
        'access' => $canAccess ? 'Toegang' : null,
    ]);
    $input = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $btn = 'rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800';
    $th = 'whitespace-nowrap px-4 py-2.5 text-left text-xs font-semibold text-slate-700';
@endphp
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<x-hr-nav />
<div class="flex shrink-0 flex-wrap items-start justify-between gap-4">
    <div class="flex items-center gap-4">
        @if ($employee->photo_path)
            <img src="{{ route('employees.photo', $employee) }}" alt="" class="size-14 rounded-full object-cover">
        @else
            <span class="grid size-14 place-items-center rounded-full bg-brand-100 text-lg font-semibold text-brand-700">{{ $employee->initials_label }}</span>
        @endif
        <div>
            @if ($me->can('viewAny', \App\Models\Employee::class))<a href="{{ route('employees.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Medewerkers</a>@endif
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $employee->full_name }}</h1>
                <x-status-badge :active="$employee->is_employed" />
                @if ($employee->left_at && $employee->is_employed)<span class="text-xs text-rose-600">Uit dienst per {{ $fmt($employee->left_at) }}</span>@endif
            </div>
            <p class="text-sm text-slate-500">{{ collect([$employee->job_title, $employee->teams->pluck('name')->implode(', '), $employee->employee_number])->filter()->implode(' · ') }}</p>
        </div>
    </div>
    @can('update', $employee)
    <div class="flex flex-wrap gap-2 text-sm">
        <a href="{{ route('employees.edit', $employee) }}" class="{{ $btn }}">Bewerken</a>
        @if ($employee->is_employed)
            <button type="button" data-toggle="offboard" class="rounded-lg px-3.5 py-2 font-medium text-rose-700 ring-1 ring-inset ring-rose-300 hover:bg-rose-50">Uit dienst melden</button>
        @else
            <form method="POST" action="{{ route('employees.reactivate', $employee) }}" onsubmit="return confirm('Deze medewerker weer in dienst melden?')">@csrf @method('PATCH')
                <button class="rounded-lg px-3.5 py-2 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-300 hover:bg-emerald-50">Weer in dienst melden</button>
            </form>
        @endif
    </div>
    @endcan
</div>

@can('update', $employee)
<form id="offboard" method="POST" action="{{ route('employees.offboard', $employee) }}" class="hidden shrink-0 flex-wrap items-end gap-3 rounded-2xl bg-rose-50 p-4 ring-1 ring-rose-200">
    @csrf
    <div><label class="mb-1 block text-xs font-medium text-slate-700">Laatste werkdag</label><input type="date" name="left_at" required value="{{ old('left_at', today()->format('Y-m-d')) }}" class="{{ $input }}"></div>
    <div class="min-w-48 flex-1"><label class="mb-1 block text-xs font-medium text-slate-700">Reden</label><input name="leave_reason" value="{{ old('leave_reason') }}" placeholder="Bijv. nieuwe baan" class="{{ $input }}"></div>
    <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Bevestigen</button>
    <p class="w-full text-xs text-slate-600">Lopende contracten eindigen op deze datum, openstaande aanvragen daarna worden ingetrokken en het account wordt geblokkeerd zodra de datum is bereikt.</p>
    @error('left_at')<p class="w-full text-xs text-rose-600">{{ $message }}</p>@enderror
</form>
@endcan

<div data-tabs data-initial="{{ $tab }}" class="flex min-h-0 flex-col lg:flex-1">
    <div class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 text-sm font-medium" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button" data-tab-target="{{ $key }}" class="-mb-px whitespace-nowrap border-b-2 border-transparent px-4 py-2.5 text-slate-500 hover:text-slate-800">{{ $label }}</button>
        @endforeach
    </div>

    <div class="min-h-0 flex-1 pt-4">
    {{-- Overzicht --}}
    <div data-tab-panel="overview" class="grid gap-4 lg:h-full {{ $canTimeline ? 'lg:grid-cols-[20rem_1fr]' : 'lg:grid-cols-3' }}">
        <div class="space-y-4 lg:overflow-y-auto lg:pr-1">
            <x-card title="Over {{ $employee->first_name }}" class="overflow-visible">
                <dl class="divide-y divide-slate-100 text-sm">
                    @if ($canPrivate)
                        {!! $row('Geslacht', ['male' => 'Man', 'female' => 'Vrouw', 'other' => 'Anders'][$employee->gender] ?? null) !!}
                        {!! $row('Leeftijd', $employee->date_of_birth ? $employee->date_of_birth->age.' jaar' : null) !!}
                        {!! $row('Geboortedatum', $employee->date_of_birth?->translatedFormat('d-m-Y')) !!}
                        {!! $row('Burgerlijke staat', \App\Models\Employee::MARITAL_STATUSES[$employee->marital_status] ?? null) !!}
                        {!! $row('Nationaliteit', $employee->nationality) !!}
                        {!! $row('BSN', $employee->bsn) !!}
                        {!! $row('IBAN', $employee->iban) !!}
                    @endif
                    {!! $row('LinkedIn', $employee->linkedin_url) !!}
                    {!! $row('Twitter', $employee->twitter_url) !!}
                    {!! $row('Facebook', $employee->facebook_url) !!}
                </dl>
                @unless ($canPrivate)<p class="text-sm text-slate-500">Persoonlijke gegevens zijn verborgen voor jouw rechten.</p>@endunless
            </x-card>
            @if ($canPrivate)
            <x-card title="Privé" class="overflow-visible">
                <dl class="divide-y divide-slate-100 text-sm">
                    {!! $row('E-mailadres (privé)', $employee->private_email) !!}
                    {!! $row('Telefoonnummer (privé)', $employee->private_phone) !!}
                    {!! $row('Adres', collect([$employee->street, trim($employee->zipcode.' '.$employee->city), $employee->country])->filter()->implode(', ')) !!}
                    {!! $row('Noodcontact', collect([$employee->emergency_contact_name, $employee->emergency_contact_phone])->filter()->implode(' · ')) !!}
                </dl>
            </x-card>
            @endif
            <x-card title="Zakelijk" class="overflow-visible">
                <dl class="divide-y divide-slate-100 text-sm">
                    {!! $row('E-mailadres (werk)', $employee->email) !!}
                    {!! $row('Telefoonnummer (werk)', $employee->phone) !!}
                    {!! $row('Mobiel (werk)', $employee->mobile) !!}
                    {!! $row('Soort medewerker', $employee->employee_type->label()) !!}
                    {!! $row('Functie', $employee->job_title) !!}
                    {!! $row('Teams', $employee->teams->pluck('name')->implode(', ')) !!}
                    {!! $row('Disciplines', $employee->disciplines->pluck('name')->implode(', ')) !!}
                    {!! $row('In dienst', $employee->hired_at?->translatedFormat('d M Y')) !!}
                    {!! $row('Uit dienst', $employee->left_at?->translatedFormat('d M Y')) !!}
                    {!! $row('Reden vertrek', $employee->leave_reason) !!}
                    @if ($employee->manager)
                        <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Leidinggevende</dt><dd class="text-right font-medium">@can('view', $employee->manager)<a href="{{ route('employees.show', $employee->manager) }}" class="text-brand-700 hover:underline">{{ $employee->manager->full_name }}</a>@else{{ $employee->manager->full_name }}@endcan</dd></div>
                    @endif
                    @if ($employee->person)
                        <div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">Persoon (CRM)</dt><dd class="text-right font-medium"><a href="{{ route('people.show', $employee->person) }}" class="text-brand-700 hover:underline">Bekijk profiel</a></dd></div>
                    @endif
                </dl>
                @if ($employee->directReports->isNotEmpty())
                    <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Directe medewerkers</h3>
                    <ul class="space-y-1 text-sm">@foreach ($employee->directReports as $report)<li>{{ $report->full_name }}</li>@endforeach</ul>
                @endif
            </x-card>
        </div>
        @if ($canTimeline)
            <x-timeline :notes="$employee->notes" :action="route('employees.notes.store', $employee)" :types="$noteTypes" class="mx-auto w-full max-w-3xl lg:h-full" />
        @endif
    </div>

    {{-- Contracten --}}
    @if ($canContracts)
    <div data-tab-panel="contracts" class="grid gap-4 lg:h-full lg:grid-cols-3">
        <x-card title="Contractgeschiedenis" flush class="lg:col-span-2">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Periode</th><th class="{{ $th }}">Soort</th><th class="{{ $th }}">Dienstverband</th><th class="{{ $th }}">Uren/week</th>@can('manageContracts', $employee)<th class="{{ $th }}">Salaris</th><th></th>@endcan</tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($employee->contracts as $contract)
                    <tr class="align-top">
                        <td class="px-4 py-2.5">{{ $fmt($contract->start_date) }} – {{ $contract->end_date ? $fmt($contract->end_date) : 'onbepaald' }}
                            @if ($contract->isCurrent())<span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Huidig</span>@elseif ($contract->isFuture())<span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Gepland</span>@endif
                            @if ($contract->remarks)<div class="text-xs text-slate-500">{{ $contract->remarks }}</div>@endif</td>
                        <td class="px-4 py-2.5">{{ $contract->contract_type->label() }}</td>
                        <td class="px-4 py-2.5">{{ $contract->employment_type->label() }}</td>
                        <td class="px-4 py-2.5">{{ $contract->hours_per_week ? $num($contract->hours_per_week) : '—' }}</td>
                        @can('manageContracts', $employee)
                        <td class="px-4 py-2.5">{{ $contract->gross_monthly_salary ? '€ '.number_format($contract->gross_monthly_salary, 2, ',', '.') : '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right">
                            <form method="POST" action="{{ route('employees.contracts.destroy', [$employee, $contract]) }}" onsubmit="return confirm('Dit contract verwijderen?')">@csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Verwijderen</button></form>
                        </td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Nog geen contracten.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
        @can('manageContracts', $employee)
        <x-card title="Nieuw contract">
            <form method="POST" action="{{ route('employees.contracts.store', $employee) }}" class="space-y-3">
                @csrf
                <x-select name="contract_type" label="Contractsoort" :options="$contractTypes" />
                <x-select name="employment_type" label="Dienstverband" :options="$employmentTypes" />
                <div class="grid grid-cols-2 gap-3"><x-field name="start_date" label="Startdatum" type="date" required /><x-field name="end_date" label="Einddatum" type="date" /></div>
                <div class="grid grid-cols-2 gap-3"><x-field name="hours_per_week" label="Uren per week" type="number" step="0.5" /><x-field name="gross_monthly_salary" label="Bruto maandsalaris" type="number" step="0.01" /></div>
                <x-field name="remarks" label="Opmerking" />
                <p class="text-xs text-slate-500">Een nieuw contract mag niet overlappen met een bestaand contract; zet eerst een einddatum op het vorige.</p>
                <button class="{{ $btn }}">Contract toevoegen</button>
            </form>
        </x-card>
        @endcan
    </div>

    {{-- Rooster --}}
    <div data-tab-panel="schedule" class="grid gap-4 lg:h-full lg:grid-cols-3">
        <x-card title="Roosters" flush class="lg:col-span-2">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Begindatum</th><th class="{{ $th }}">Einddatum</th><th class="{{ $th }}">Rooster</th><th class="{{ $th }} text-right">Kostprijs</th><th class="{{ $th }} text-right">Verkooptarief</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($employee->schedules as $schedule)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-4 py-2.5">{{ $fmt($schedule->effective_from) }}
                            @if ($currentSchedule?->is($schedule))<span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Huidig</span>@elseif ($schedule->effective_from->isFuture())<span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Gepland</span>@endif</td>
                        <td class="whitespace-nowrap px-4 py-2.5">{{ ($end = $employee->scheduleEnd($schedule)) ? $fmt($end) : '' }}</td>
                        <td class="px-4 py-2.5">
                            {{ number_format($schedule->weeklyHours('odd'), 2, ',', '.') }}@if ($schedule->alternating) / {{ number_format($schedule->weeklyHours('even'), 2, ',', '.') }}@endif uur per week
                            <div class="text-xs text-slate-500">{{ $schedule->alternating ? 'Even/oneven afwijkend · ' : '' }}@foreach ($schedule->pattern['odd'] ?? [] as $d => $day){{ substr($weekdays[$d], 0, 2) }} {{ $day['start'] }}–{{ $day['end'] }}{{ ! $loop->last ? ', ' : '' }}@endforeach</div></td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right">{{ $schedule->cost_rate !== null ? '€ '.number_format($schedule->cost_rate, 2, ',', '.') : '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right">{{ $schedule->sales_rate !== null ? '€ '.number_format($schedule->sales_rate, 2, ',', '.') : '—' }}</td>
                        <td class="px-4 py-2.5 text-right">@can('manageContracts', $employee)<form method="POST" action="{{ route('employees.schedules.destroy', [$employee, $schedule]) }}" onsubmit="return confirm('Dit rooster verwijderen?')">@csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Verwijderen</button></form>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Nog geen rooster.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
        @can('manageContracts', $employee)
        <x-card title="Nieuw rooster">
            <form method="POST" action="{{ route('employees.schedules.store', $employee) }}" class="space-y-3" data-schedule>
                @csrf
                <div class="grid grid-cols-3 gap-3">
                    <x-field name="effective_from" label="Begindatum" type="date" :value="today()->format('Y-m-d')" required />
                    <x-field name="cost_rate" label="Kostprijs" type="number" step="0.01" min="0" required placeholder="€" />
                    <x-field name="sales_rate" label="Verkooptarief" type="number" step="0.01" min="0" required placeholder="€" />
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="alternating" value="1" data-reveal="even-week" @checked(old('alternating')) class="rounded border-slate-300 text-brand-600"> Oneven en even weken afwijkend</label>
                @error('days')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                @foreach (['odd' => 'Oneven weken (of elke week)', 'even' => 'Even weken'] as $week => $weekLabel)
                    <fieldset @if ($week === 'even') id="even-week" @endif>
                        <legend class="mb-1 text-sm font-semibold">{{ $weekLabel }}</legend>
                        <div class="space-y-1">
                            @foreach ($weekdays as $d => $dayName)
                                <div class="grid grid-cols-[5.5rem_1fr_1fr_4.5rem] items-center gap-2 text-sm">
                                    <span class="text-slate-600">{{ $dayName }}</span>
                                    <input type="time" name="days[{{ $week }}][{{ $d }}][start]" value="{{ old("days.$week.$d.start", $d <= 5 && $week === 'odd' ? '09:00' : '') }}" class="{{ $input }} !py-1.5">
                                    <input type="time" name="days[{{ $week }}][{{ $d }}][end]" value="{{ old("days.$week.$d.end", $d <= 5 && $week === 'odd' ? '17:30' : '') }}" class="{{ $input }} !py-1.5">
                                    <input type="number" min="0" max="480" step="5" name="days[{{ $week }}][{{ $d }}][break]" value="{{ old("days.$week.$d.break", $d <= 5 && $week === 'odd' ? 30 : '') }}" placeholder="pauze" class="{{ $input }} !py-1.5" title="Pauze in minuten">
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                <p class="text-xs text-slate-500">Laat een dag leeg voor een vrije dag; pauze in minuten. De einddatum volgt automatisch uit de begindatum van het volgende rooster, zodat toekomstige wijzigingen vanzelf ingaan.</p>
                <button class="{{ $btn }}">Rooster opslaan</button>
            </form>
        </x-card>
        @endcan
    </div>
    @endif

    {{-- Verlof --}}
    @if ($canLeave)
    <div data-tab-panel="leave" class="grid gap-4 lg:h-full lg:grid-cols-3 lg:grid-rows-[auto_1fr]">
        @php
            $now = today();
            $vac = \App\Enums\BalanceKind::Vacation;
            $tvtKind = \App\Enums\BalanceKind::TimeForTime;
            $summary = [
                ['Huidig saldo', $employee->balanceAsOf($vac, $now)],
                ['Gepland verlof', $employee->plannedHours($vac)],
                ['Saldo einde jaar', $employee->balanceAsOf($vac, $now->copy()->endOfYear())],
                ['Tijd-voor-tijd', $employee->balanceAsOf($tvtKind, $now)],
            ];
        @endphp
        <div class="grid grid-cols-2 gap-3 lg:col-span-3 lg:grid-cols-5">
            @foreach ($summary as [$label, $value])
                <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200">
                    <div class="text-xs text-slate-500">{{ $label }}</div>
                    <div class="text-xl font-semibold {{ $value < 0 ? 'text-rose-600' : 'text-ink' }}">{{ $num($value) }} uur</div>
                    <div class="text-sm text-slate-500">{{ $num($employee->toDays($value)) }} dagen</div>
                </div>
            @endforeach
            <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">Aanvragen in afwachting</div><div class="text-xl font-semibold">{{ $employee->leaveRequests->where('status', \App\Enums\LeaveStatus::Pending)->count() }}</div><div class="text-sm text-slate-500">{{ $currentSchedule ? $num($currentSchedule->weeklyHours('odd')) : '—' }} u per week</div></div>
        </div>
        <x-card title="Verlofaanvragen" flush class="lg:col-span-2">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Periode</th><th class="{{ $th }}">Soort</th><th class="{{ $th }}">Uren</th><th class="{{ $th }}">Dagen</th><th class="{{ $th }}">Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($employee->leaveRequests as $request)
                    <tr class="align-top">
                        <td class="px-4 py-2.5">{{ $fmt($request->start_date) }} – {{ $fmt($request->end_date) }}@if ($request->note)<div class="text-xs text-slate-500">{{ $request->note }}</div>@endif</td>
                        <td class="px-4 py-2.5">{{ $request->leaveType->name }}</td>
                        <td class="px-4 py-2.5">{{ $num($request->hours) }}</td>
                        <td class="px-4 py-2.5">{{ $num($employee->toDays((float) $request->hours)) }}</td>
                        <td class="px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-rose-50 text-rose-700', 'cancelled' => 'bg-slate-100 text-slate-600'][$request->status->value] }}">{{ $request->status->label() }}</span>
                            @if ($request->decision_note)<div class="mt-1 text-xs text-slate-500">{{ $request->decision_note }}</div>@endif</td>
                        <td class="px-4 py-2.5 text-right">
                            @if (in_array($request->status, [\App\Enums\LeaveStatus::Pending, \App\Enums\LeaveStatus::Approved], true) && ($me->can('requestLeave', $employee) || $me->can('manageLeave', $employee)))
                                <form method="POST" action="{{ route('leave-requests.cancel', $request) }}" onsubmit="return confirm('Dit verlof intrekken?')">@csrf @method('PATCH')<button class="text-xs text-rose-600 hover:underline">Intrekken</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Nog geen verlofaanvragen.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
        <div class="flex min-h-0 flex-col gap-4">
            @can('requestLeave', $employee)
            <x-card title="Verlof aanvragen">
                <form method="POST" action="{{ route('employees.leave.store', $employee) }}" class="space-y-3">
                    @csrf
                    <x-select name="leave_type_id" label="Soort verlof" :options="$leaveTypes" />
                    <div class="grid grid-cols-2 gap-3"><x-field name="start_date" label="Van" type="date" required /><x-field name="end_date" label="Tot en met" type="date" required /></div>
                    <x-field name="hours" label="Uren (optioneel)" type="number" step="0.25" hint="Leeg = automatisch uit het rooster." />
                    <x-field name="note" label="Toelichting" />
                    <button class="{{ $btn }}">Aanvragen</button>
                </form>
            </x-card>
            @endcan
            @can('manageLeave', $employee)
            <x-card title="Saldo aanpassen">
                <form method="POST" action="{{ route('employees.balance.store', $employee) }}" class="space-y-3">
                    @csrf
                    <x-select name="action" label="Actie" :options="$ledgerActions" />
                    <x-select name="kind" label="Saldo (alleen bij correctie)" :options="['vacation' => 'Vakantie-uren', 'tvt' => 'Tijd-voor-tijd']" />
                    <div class="grid grid-cols-2 gap-3"><x-field name="hours" label="Uren" type="number" step="0.25" required /><x-field name="entry_date" label="Datum" type="date" :value="today()->format('Y-m-d')" required /></div>
                    <x-field name="description" label="Omschrijving" />
                    <button class="{{ $btn }}">Opslaan</button>
                </form>
            </x-card>
            @endcan
        </div>
        <x-card title="Saldomutaties" flush class="lg:col-span-3 lg:max-h-72">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Datum</th><th class="{{ $th }}">Saldo</th><th class="{{ $th }}">Reden</th><th class="{{ $th }}">Omschrijving</th><th class="{{ $th }} text-right">Uren</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($employee->ledgerEntries as $entry)
                    <tr><td class="px-4 py-2">{{ $fmt($entry->entry_date) }}</td><td class="px-4 py-2">{{ $entry->kind->label() }}</td><td class="px-4 py-2">{{ $entry->reason->label() }}</td><td class="px-4 py-2 text-slate-600">{{ $entry->description }}</td>
                        <td class="px-4 py-2 text-right font-medium {{ $entry->hours < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $entry->hours > 0 ? '+' : '' }}{{ $num($entry->hours) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Nog geen mutaties.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
    @endif

    {{-- Verzuim --}}
    @if ($canAbsence)
    <div data-tab-panel="absence" class="grid gap-4 lg:h-full lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2 lg:flex lg:min-h-0 lg:flex-col">
        <div class="grid shrink-0 grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([['Verzuim vorig jaar', now()->year - 1], ['Verzuim dit jaar', now()->year]] as [$label, $year])
                <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">{{ $label }}</div><div class="text-xl font-semibold">{{ $num($employee->absenceStats($year)['hours']) }} uur</div><div class="text-sm text-slate-500">{{ $num($employee->absenceStats($year)['percentage']) }}%</div></div>
            @endforeach
        </div>
        <x-card title="Ziekte en verzuim" flush class="lg:flex-1">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Periode</th><th class="{{ $th }}">Soort</th><th class="{{ $th }}">%</th><th class="{{ $th }}">Uren</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($employee->absences as $absence)
                    <tr class="align-top">
                        <td class="px-4 py-2.5">{{ $fmt($absence->start_date) }} – {{ $absence->end_date ? $fmt($absence->end_date) : 'lopend' }}@if ($absence->note)<div class="text-xs text-slate-500">{{ $absence->note }}</div>@endif</td>
                        <td class="px-4 py-2.5">{{ $absence->type->label() }}</td>
                        <td class="px-4 py-2.5">{{ $absence->percentage }}%</td>
                        <td class="px-4 py-2.5">{{ $num($employee->scheduledHours($absence->start_date, $absence->end_date ?? today()) * $absence->percentage / 100) }}</td>
                        <td class="px-4 py-2.5 text-right">
                            @can('manageAbsence', $employee)
                                @if ($absence->isOngoing())
                                    <form method="POST" action="{{ route('absences.update', $absence) }}" class="flex items-center justify-end gap-2">@csrf @method('PUT')
                                        <input type="date" name="end_date" required value="{{ today()->format('Y-m-d') }}" class="{{ $input }} !w-36 !py-1">
                                        <button class="whitespace-nowrap text-xs font-medium text-brand-700 hover:underline">Hersteld</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('absences.destroy', $absence) }}" onsubmit="return confirm('Deze melding verwijderen?')" class="mt-1">@csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Verwijderen</button></form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Geen verzuim geregistreerd.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
        </div>
        @can('manageAbsence', $employee)
        <x-card title="Ziek- of verzuimmelding">
            <form method="POST" action="{{ route('absences.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <x-select name="type" label="Soort" :options="$absenceTypes" />
                <div class="grid grid-cols-2 gap-3"><x-field name="start_date" label="Eerste ziektedag" type="date" :value="today()->format('Y-m-d')" required /><x-field name="end_date" label="Hersteld op" type="date" /></div>
                <x-field name="percentage" label="Arbeidsongeschiktheid (%)" type="number" min="1" max="100" value="100" required />
                <x-field name="note" label="Opmerking" />
                <button class="{{ $btn }}">Registreren</button>
            </form>
        </x-card>
        @endcan
    </div>
    @endif

    {{-- Personeelsdossier --}}
    @if ($canFile)
    <x-documents data-tab-panel="file" class="lg:h-full" :documents="$employee->documents" :action="route('employees.documents.store', $employee)" :categories="$documentCategories" :can-manage="$me->can('manageDocuments', $employee)" />
    @endif

    {{-- Toegang --}}
    @if ($canAccess)
    <div data-tab-panel="access" class="grid gap-4 lg:h-full lg:grid-cols-2 lg:content-start">
        <x-card title="Inloggegevens en rechten">
            @if ($employee->user)
                <dl class="mb-4 divide-y divide-slate-100 text-sm">
                    {!! $row('Account', $employee->user->email) !!}
                    {!! $row('Gebruikersgroep', $employee->user->group?->name ?? 'Geen') !!}
                    {!! $row('Status', $employee->user->invited_at ? 'Uitgenodigd, nog niet geactiveerd' : ($employee->user->is_active ? 'Actief' : 'Geblokkeerd')) !!}
                </dl>
                <form method="POST" action="{{ route('employees.access', $employee) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <x-select name="user_group_id" label="Gebruikersgroep" placeholder="— Geen —" :options="$groups" :selected="$employee->user->user_group_id" />
                    <x-select name="is_active" label="Account" :options="['1' => 'Actief', '0' => 'Geblokkeerd']" :selected="$employee->user->is_active ? '1' : '0'" />
                    <button class="{{ $btn }}">Opslaan</button>
                </form>
            @else
                <p class="text-sm text-slate-600">Deze medewerker heeft nog geen account.</p>
            @endif
        </x-card>
        <x-card title="{{ $employee->user ? 'Opnieuw uitnodigen' : 'Uitnodigen' }}">
            <form method="POST" action="{{ route('employees.invite', $employee) }}" class="space-y-3">
                @csrf
                <x-select name="user_group_id" label="Gebruikersgroep" placeholder="— Ongewijzigd / geen —" :options="$groups" />
                <p class="text-xs text-slate-500">Er wordt een activatiemail gestuurd naar {{ $employee->email }}. De link is 7 dagen geldig.</p>
                <button class="{{ $btn }}">Uitnodiging versturen</button>
            </form>
        </x-card>
    </div>
    @endif
    </div>
</div>
</div>
@endsection

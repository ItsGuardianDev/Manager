@extends('layouts.app')
@section('title', 'Urenrapportage')
@section('content')
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') ?: '0';
    $money = fn ($v) => \Illuminate\Support\Number::currency((float) $v, 'EUR', 'nl');
    $field = 'rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $th = 'px-4 py-2.5 text-left text-xs font-semibold text-slate-700';
@endphp
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
        <x-hours-nav active="report" />
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('hours.report.export', request()->query()) }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Exporteren naar Excel</a>
            <a href="{{ route('hours.report.export', [...request()->query(), 'format' => 'csv']) }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">CSV</a>
        </div>
    </div>

    <form method="GET" data-autosubmit class="flex shrink-0 flex-wrap items-end gap-2 text-sm">
        @if ($canSeeOthers)<select name="employee_id" class="{{ $field }}"><option value="">Alle medewerkers</option>@foreach ($employeeOptions as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select>@endif
        <select name="project_id" class="{{ $field }}"><option value="">Alle projecten</option>@foreach ($projectOptions as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select>
        <select name="service" class="{{ $field }}"><option value="">Alle diensten</option>@foreach ($serviceOptions as $name)<option value="{{ $name }}" @selected(request('service') === $name)>{{ $name }}</option>@endforeach</select>
        <select name="hour_type_id" class="{{ $field }}"><option value="">Alle urensoorten</option>@foreach ($hourTypeOptions as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['hour_type_id'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select>
        <select name="status" class="{{ $field }}"><option value="">Alle statussen</option>@foreach ($statusOptions as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="{{ $field }}" title="Vanaf"><input type="date" name="to" value="{{ $filters['to'] }}" class="{{ $field }}" title="Tot en met">
    </form>

    <div class="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['Uren in periode', $num($totalHours)], ['Waarde (uren × tarief)', $money($totalAmount)], ['Medewerkers', count($byEmployee)], ['Projecten', count($byProject)]] as [$label, $value])
            <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-xl font-semibold">{{ $value }}</div><div class="text-xs text-slate-500">{{ $label }}</div></div>
        @endforeach
    </div>

    <div class="grid min-h-0 gap-4 overflow-y-auto lg:flex-1 lg:grid-cols-2 lg:content-start">
        <x-card title="Per medewerker" flush class="lg:col-span-2">
            <table class="min-w-full text-sm"><thead class="bg-brand-50"><tr><th class="{{ $th }}">Medewerker</th><th class="{{ $th }} text-right">Geregistreerd</th><th class="{{ $th }} text-right">Goedgekeurd</th><th class="{{ $th }} text-right">Ingediend</th><th class="{{ $th }} text-right">Concept</th><th class="{{ $th }} text-right">Rooster</th><th class="{{ $th }} text-right">Verschil</th><th class="{{ $th }} text-right">Overuren (tvt)</th></tr></thead>
                <tbody class="divide-y divide-slate-100">@forelse ($byEmployee as $row)<tr><td class="px-4 py-2">{{ $row['name'] }}</td><td class="px-4 py-2 text-right font-medium">{{ $num($row['hours']) }}</td><td class="px-4 py-2 text-right">{{ $num($row['approved']) }}</td><td class="px-4 py-2 text-right">{{ $num($row['submitted']) }}</td><td class="px-4 py-2 text-right">{{ $num($row['draft']) }}</td><td class="px-4 py-2 text-right text-slate-500">{{ $num($row['scheduled']) }}</td><td class="px-4 py-2 text-right {{ $row['difference'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ ($row['difference'] > 0 ? '+' : '').$num($row['difference']) }}</td><td class="px-4 py-2 text-right">{{ $num($row['overtime']) }}</td></tr>@empty<tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">Geen uren in deze periode.</td></tr>@endforelse</tbody></table>
        </x-card>
        <x-card title="Per project" flush class="lg:max-h-80"><table class="min-w-full text-sm"><thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Project</th><th class="{{ $th }} text-right">Uren</th><th class="{{ $th }} text-right">Waarde</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach ($byProject as $row)<tr><td class="px-4 py-2">{{ $row['number'] }} · {{ $row['name'] }}</td><td class="px-4 py-2 text-right font-medium">{{ $num($row['hours']) }}</td><td class="px-4 py-2 text-right text-slate-500">{{ $money($row['amount']) }}</td></tr>@endforeach</tbody></table></x-card>
        <x-card title="Per urensoort" flush class="lg:max-h-80"><table class="min-w-full text-sm"><thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Urensoort</th><th class="{{ $th }} text-right">Uren</th><th class="{{ $th }} text-right">Waarde</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach ($byHourType as $row)<tr><td class="px-4 py-2">{{ $row['name'] }}</td><td class="px-4 py-2 text-right font-medium">{{ $num($row['hours']) }}</td><td class="px-4 py-2 text-right text-slate-500">{{ $money($row['amount']) }}</td></tr>@endforeach</tbody></table></x-card>
        <x-card title="Per dienst" flush class="lg:col-span-2 lg:max-h-80"><table class="min-w-full text-sm"><thead class="sticky top-0 bg-brand-50"><tr><th class="{{ $th }}">Project</th><th class="{{ $th }}">Dienst</th><th class="{{ $th }} text-right">Uren</th><th class="{{ $th }} text-right">Waarde</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach ($byService as $row)<tr><td class="px-4 py-2">{{ $row['project'] }}</td><td class="px-4 py-2">{{ $row['name'] }}</td><td class="px-4 py-2 text-right font-medium">{{ $num($row['hours']) }}</td><td class="px-4 py-2 text-right text-slate-500">{{ $money($row['amount']) }}</td></tr>@endforeach</tbody></table></x-card>
    </div>
</div>
@endsection

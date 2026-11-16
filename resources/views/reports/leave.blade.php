@extends('layouts.app')
@section('title', 'Verlof- en verzuimrapportage')
@section('content')
@php($num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ','))
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<x-hr-nav />
<div class="flex shrink-0 flex-wrap items-end justify-between gap-3">
    <h1 class="text-2xl font-semibold tracking-tight">Verlof- en verzuimrapportage {{ $year }}</h1>
    <form method="GET" class="flex flex-wrap items-center gap-2 text-sm" data-autosubmit>
        <select name="year" class="rounded-full border-0 bg-white px-4 py-2 shadow-sm ring-1 ring-inset ring-slate-300">@foreach (range(now()->year + 1, now()->year - 5) as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach</select>
        <select name="team_id" class="rounded-full border-0 bg-white px-4 py-2 shadow-sm ring-1 ring-inset ring-slate-300"><option value="">Alle teams</option>@foreach ($teams as $id => $name)<option value="{{ $id }}" @selected((string) request('team_id') === (string) $id)>{{ $name }}</option>@endforeach</select>
        <a href="{{ route('reports.leave.export', request()->query()) }}" class="rounded-full bg-brand-700 px-4 py-2 font-semibold text-white hover:bg-brand-800">Exporteren naar Excel</a>
    </form>
</div>
<div class="grid shrink-0 grid-cols-3 gap-3">
    <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-2xl font-semibold">{{ $num($totals['taken']) }} u</div><div class="text-xs text-slate-500">Verlof opgenomen</div></div>
    <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-2xl font-semibold">{{ $num($totals['absence_hours']) }} u</div><div class="text-xs text-slate-500">Verzuimuren</div></div>
    <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200"><div class="text-2xl font-semibold">{{ $totals['scheduled'] > 0 ? $num($totals['absence_hours'] / $totals['scheduled'] * 100) : 0 }}%</div><div class="text-xs text-slate-500">Verzuimpercentage</div></div>
</div>
<x-card flush class="lg:flex-1">
    <table class="min-w-full text-sm">
        <thead class="sticky top-0 z-10 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr>
            <th class="px-4 py-3">Medewerker</th><th class="px-4 py-3">Team</th><th class="px-4 py-3 text-right">Toegekend</th><th class="px-4 py-3 text-right">Opgenomen</th><th class="px-4 py-3 text-right">Saldo vakantie</th><th class="px-4 py-3 text-right">Saldo TvT</th><th class="px-4 py-3 text-right">Meldingen</th><th class="px-4 py-3 text-right">Verzuimuren</th><th class="px-4 py-3 text-right">Verzuim %</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($rows as $row)
            <tr><td class="px-4 py-2.5 font-medium"><a href="{{ route('employees.show', [$row['employee'], 'tab' => 'leave']) }}" class="hover:text-brand-700 hover:underline">{{ $row['employee']->full_name }}</a></td>
                <td class="px-4 py-2.5">{{ $row['employee']->team?->name ?? '—' }}</td>
                <td class="px-4 py-2.5 text-right">{{ $num($row['allocated']) }}</td><td class="px-4 py-2.5 text-right">{{ $num($row['taken']) }}</td>
                <td class="px-4 py-2.5 text-right {{ $row['vacation_balance'] < 0 ? 'text-rose-600' : '' }}">{{ $num($row['vacation_balance']) }}</td><td class="px-4 py-2.5 text-right">{{ $num($row['tvt_balance']) }}</td>
                <td class="px-4 py-2.5 text-right">{{ $row['absence_count'] }}</td><td class="px-4 py-2.5 text-right">{{ $num($row['absence_hours']) }}</td><td class="px-4 py-2.5 text-right">{{ $num($row['absence_percentage']) }}%</td></tr>
        @empty
            <tr><td colspan="9" class="px-4 py-12 text-center text-slate-500">Geen medewerkers gevonden.</td></tr>
        @endforelse
        </tbody>
    </table>
</x-card>
</div>
@endsection

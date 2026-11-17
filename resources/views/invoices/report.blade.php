@extends('layouts.app')
@section('title', 'Omzetrapportage')
@section('content')
@php($money = fn ($value) => $value == 0 ? '—' : \Illuminate\Support\Number::currency($value, 'EUR', 'nl'))
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
    <x-invoice-nav active="report" />
    <form method="GET" class="flex flex-wrap items-center gap-2 text-sm">
        <select name="group" onchange="this.form.submit()" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300" aria-label="Groeperen op">@foreach ($groups as $value => $label)<option value="{{ $value }}" @selected($group === $value)>Groeperen op {{ strtolower($label) }}</option>@endforeach</select>
        <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" onchange="this.form.submit()" class="w-24 rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300" aria-label="Jaar">
        <a href="{{ route('invoices.report', ['group' => $group, 'year' => $year, 'export' => 1]) }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Exporteren</a>
    </form>
</div>
<div class="shrink-0"><span class="text-xs text-slate-500">Omzet {{ $year }} (excl. btw)</span> <span class="text-lg font-semibold">{{ \Illuminate\Support\Number::currency($grandTotal, 'EUR', 'nl') }}</span></div>
<div class="flex min-h-[20rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="px-4 py-3">{{ $groups[$group] }}</th>@foreach (range(1, 12) as $month)<th class="px-3 py-3 text-right">{{ \Illuminate\Support\Carbon::create($year, $month, 1)->translatedFormat('M') }}</th>@endforeach<th class="px-4 py-3 text-right">Totaal</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($rows as $row)
                <tr><td class="px-4 py-2.5 font-medium">{{ $row['label'] }}</td>@foreach ($row['months'] as $amount)<td class="px-3 py-2.5 text-right">{{ $money($amount) }}</td>@endforeach<td class="px-4 py-2.5 text-right font-semibold">{{ $money($row['total']) }}</td></tr>
            @empty
                <tr><td colspan="14" class="px-4 py-12 text-center text-slate-500">Geen verzonden facturen in {{ $year }}.</td></tr>
            @endforelse
            </tbody>
            @if ($rows)<tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold"><tr><td class="px-4 py-2.5">Totaal</td>@foreach ($totals as $amount)<td class="px-3 py-2.5 text-right">{{ $money($amount) }}</td>@endforeach<td class="px-4 py-2.5 text-right">{{ $money($grandTotal) }}</td></tr></tfoot>@endif
        </table>
    </div>
</div>
</div>
@endsection

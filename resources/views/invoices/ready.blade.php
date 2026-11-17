@extends('layouts.app')
@section('title', 'Facturen')
@section('content')
@php($money = fn ($value) => \Illuminate\Support\Number::currency($value, 'EUR', 'nl'))
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
    <x-invoice-nav active="ready" />
    <div class="flex items-center gap-2 text-sm">
        <form method="GET" class="flex gap-2"><input type="hidden" name="tab" value="ready"><input type="search" name="q" value="{{ request('q') }}" placeholder="Zoeken…" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600"></form>
        @can('invoices.manage')<a href="{{ route('invoices.create') }}" class="rounded-full bg-brand-700 px-4 py-2 font-semibold text-white hover:bg-brand-800">+ Nieuwe factuur</a>@endcan
    </div>
</div>
<div class="flex shrink-0 gap-8 text-sm">
    <div><span class="text-xs text-slate-500">Totaal te factureren</span><div class="text-lg font-semibold">{{ $money(collect($rows)->sum('ready')) }}</div></div>
    <div><span class="text-xs text-slate-500">Totaal niet gereed</span><div class="text-lg font-semibold">{{ $money(collect($rows)->sum('notReady')) }}</div></div>
</div>
<div class="flex min-h-[20rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="px-4 py-3">Type</th><th class="px-4 py-3">Debiteur</th><th class="px-4 py-3">Project</th><th class="px-4 py-3">Oudste gereed voor facturatie</th><th class="px-4 py-3 text-right">Gereed</th><th class="px-4 py-3 text-right">Niet gereed</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($rows as $row)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2.5">@foreach ($row['types'] as $type)<span class="mr-1 rounded border border-slate-300 px-1.5 py-0.5 text-xs text-slate-600">{{ \App\Models\InvoiceLine::TYPES[$type] ?? $type }}</span>@endforeach</td>
                    <td class="px-4 py-2.5">{{ $row['project']->organization?->name }}</td>
                    <td class="px-4 py-2.5"><a href="{{ route('invoices.proposal', $row['project']) }}" class="font-medium text-brand-700 hover:underline">{{ $row['project']->name }}</a></td>
                    <td class="px-4 py-2.5">{{ $row['oldest']?->format('d-m-Y') ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right">{{ $money($row['ready']) }}</td><td class="px-4 py-2.5 text-right">{{ $money($row['notReady']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">Er is niets te factureren. Goedgekeurde uren en diensten met een vaste prijs of abonnement verschijnen hier vanzelf.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</div>
@endsection

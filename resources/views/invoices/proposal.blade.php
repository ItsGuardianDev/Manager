@extends('layouts.app')
@section('title', $project->name)
@section('content')
@php($money = fn ($value) => \Illuminate\Support\Number::currency($value, 'EUR', 'nl'))
@php($lineTotal = fn (array $item) => round($item['quantity'] * $item['unit_price'] * (1 - $item['discount'] / 100), 2))
<div class="flex flex-col gap-4 p-4 sm:p-5">
    <div><a href="{{ route('invoices.index', ['tab' => 'ready']) }}" class="text-sm text-slate-500 hover:text-slate-800">← Te factureren</a><h1 class="text-2xl font-semibold tracking-tight">{{ $project->name }} <span class="text-base font-normal text-slate-500">{{ $project->organization?->name }}</span></h1></div>
    @if ($errors->any())<div class="rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('invoices.from-proposal', $project) }}" id="proposal" class="space-y-6">
        @csrf
        <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <h2 class="px-5 pt-4 text-base font-semibold text-brand-700">Gereed voor facturatie</h2>
            <table class="mt-2 min-w-full text-sm">
                <thead class="bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="w-10 px-4 py-3"></th><th class="px-4 py-3">Datum</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Dienst</th><th class="px-4 py-3 text-right">Aantal</th><th class="px-4 py-3 text-right">Prijs</th><th class="px-4 py-3 text-right">Totaal</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($ready as $item)
                    <tr><td class="px-4 py-2.5"><input type="checkbox" name="keys[]" value="{{ $item['key'] }}" class="rounded border-slate-300" @disabled(! auth()->user()->can('invoices.manage'))></td><td class="px-4 py-2.5">{{ $item['date']?->format('d-m-Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5">{{ \App\Models\InvoiceLine::TYPES[$item['type']] }}</td><td class="px-4 py-2.5">{{ $item['description'] }}</td>
                        <td class="px-4 py-2.5 text-right">{{ number_format($item['quantity'], 2, ',', '.') }}</td><td class="px-4 py-2.5 text-right">{{ $money($item['unit_price']) }}</td><td class="px-4 py-2.5 text-right">{{ $money($lineTotal($item)) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Niets gereed voor facturatie.</td></tr>
                @endforelse
                </tbody>
            </table>
            @can('invoices.manage')
            @if ($ready->isNotEmpty())<div class="flex gap-2 border-t border-slate-100 px-5 py-3"><button class="rounded-full bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Maak factuur</button><button formaction="{{ route('invoices.write-off', $project) }}" class="rounded-full bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50" onclick="return confirm('Geselecteerde uren afboeken? Ze worden niet gefactureerd.')">Afboeken</button></div>@endif
            @endcan
        </section>
        <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <h2 class="px-5 pt-4 text-base font-semibold text-slate-600">Nog niet gereed voor facturatie</h2>
            <table class="mt-2 min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                @forelse ($notReady as $item)
                    <tr class="text-slate-500"><td class="px-4 py-2.5">{{ $item['date']?->format('d-m-Y') ?? '—' }}</td><td class="px-4 py-2.5">{{ \App\Models\InvoiceLine::TYPES[$item['type']] }}</td><td class="px-4 py-2.5">{{ $item['description'] }}</td>
                        <td class="px-4 py-2.5">{{ $item['type'] === 'hours' ? 'Uren nog niet goedgekeurd' : 'Factuurdatum nog niet bereikt' }}</td><td class="px-4 py-2.5 text-right">{{ $money($lineTotal($item)) }}</td></tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-slate-500">Geen regels in afwachting.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </form>
</div>
@endsection

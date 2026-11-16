@extends('layouts.app')
@section('title', 'Debiteuren')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-2xl font-semibold tracking-tight">Debiteuren</h1><p class="text-sm text-slate-500">Facturatievoorkeuren van je klanten. Openstaande facturen verschijnen hier zodra de factuurmodule er is.</p></div>
    <form method="GET" class="flex gap-2"><input type="search" name="q" value="{{ request('q') }}" placeholder="Zoeken…" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600"><button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Zoeken</button></form>
</div>
<div class="flex min-h-[24rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr>
                <th class="px-4 py-3"><x-sort-link column="name" :sort="$sort" :direction="$direction">Debiteur ({{ $debtors->total() }})</x-sort-link></th>
                <th class="px-4 py-3">Factuur t.a.v.</th><th class="px-4 py-3">Verstrekking</th><th class="px-4 py-3"><x-sort-link column="payment_term_days" :sort="$sort" :direction="$direction">Betaaltermijn</x-sort-link></th><th class="px-4 py-3">Btw-nummer</th><th class="px-4 py-3">IBAN</th><th class="px-4 py-3">Herinneringsplan</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($debtors as $debtor)
                <tr class="align-top hover:bg-slate-50">
                    <td class="px-4 py-3"><a href="{{ route('organizations.show', [$debtor, 'tab' => 'billing']) }}" class="font-medium hover:text-brand-700 hover:underline">{{ $debtor->name }}</a><div class="text-xs text-slate-500">{{ $debtor->type->label() }}</div></td>
                    <td class="px-4 py-3">{{ $debtor->invoice_attention->label() }}@if ($debtor->invoiceContactPerson)<div class="text-xs text-slate-500">{{ $debtor->invoiceContactPerson->full_name }}</div>@endif</td>
                    <td class="px-4 py-3">{{ $debtor->invoice_delivery?->label() ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $debtor->payment_term_days !== null ? (\App\Models\Organization::PAYMENT_TERMS[$debtor->payment_term_days] ?? $debtor->payment_term_days.' dagen') : '—' }}</td>
                    <td class="px-4 py-3">{{ $debtor->vat_number ?? '—' }}@if ($debtor->is_vat_exempt)<div class="text-xs text-slate-500">Vrijgesteld van btw</div>@endif</td>
                    <td class="px-4 py-3">{{ $debtor->iban ?? '—' }}</td>
                    <td class="px-4 py-3">{{ \App\Models\Organization::REMINDER_PLANS[$debtor->reminder_plan] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">Geen debiteuren gevonden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-pager :paginator="$debtors" />
</div>
</div>
@endsection

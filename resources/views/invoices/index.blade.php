@extends('layouts.app')
@section('title', 'Facturen')
@section('content')
@php($money = fn ($value) => \Illuminate\Support\Number::currency($value, 'EUR', 'nl'))
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
    <x-invoice-nav :active="$tab" />
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <form method="GET" class="flex flex-wrap gap-2"><input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Debiteur, project of nummer…" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600">
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300" aria-label="Vanaf">
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300" aria-label="Tot en met">
            @if ($tab === 'outstanding')<select name="overdue" onchange="this.form.submit()" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300">@foreach ($overdueFilters as $value => $label)<option value="{{ $value }}" @selected(request('overdue', '') === $value)>{{ $label }}</option>@endforeach</select>@endif
            @if ($tab === 'all')<select name="status" onchange="this.form.submit()" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300"><option value="">Alle statussen</option>@foreach (['draft' => 'Concept', 'sent' => 'Verzonden', 'paid' => 'Betaald'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>@endif
            <button class="rounded-lg bg-white px-3 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Filteren</button>
        </form>
        <a href="{{ route('invoices.export', request()->query()) }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Exporteren</a>
        @can('invoices.manage')<a href="{{ route('invoices.create') }}" class="rounded-full bg-brand-700 px-4 py-2 font-semibold text-white hover:bg-brand-800">+ Nieuwe factuur</a>@endcan
    </div>
</div>
@if ($errors->any())<div class="shrink-0 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
@if ($tab === 'outstanding')<div class="shrink-0"><span class="text-xs text-slate-500">Totaal openstaand</span> <span class="text-lg font-semibold">{{ $money($outstandingTotal) }}</span></div>@endif
<form method="POST" action="{{ route('invoices.send-many') }}" class="flex min-h-[20rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    @csrf
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr>
                @if ($tab === 'drafts' && auth()->user()->can('invoices.manage'))<th class="w-10 px-4 py-3"></th>@endif
                <th class="px-4 py-3">Datum — nummer</th><th class="px-4 py-3">Debiteur — project</th><th class="px-4 py-3 text-right">Totaal incl. btw</th>
                @if ($tab !== 'drafts')<th class="px-4 py-3 text-right">Openstaand</th><th class="px-4 py-3">Vervaldagen</th><th class="px-4 py-3">Eerstvolgende actie</th>@endif
                <th class="px-4 py-3">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($invoices as $invoice)
                <tr class="hover:bg-slate-50">
                    @if ($tab === 'drafts' && auth()->user()->can('invoices.manage'))<td class="px-4 py-2.5"><input type="checkbox" name="ids[]" value="{{ $invoice->id }}" class="rounded border-slate-300"></td>@endif
                    <td class="px-4 py-2.5"><a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-brand-700 hover:underline">{{ $invoice->invoice_date->format('d-m-Y') }} — {{ $invoice->label() }}</a>@if ($invoice->isCredit())<span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs text-rose-700">Credit</span>@endif</td>
                    <td class="px-4 py-2.5">{{ $invoice->organization->name }}@if ($invoice->project)<span class="text-slate-500"> — {{ $invoice->project->name }}</span>@endif</td>
                    <td class="px-4 py-2.5 text-right">{{ $money($invoice->total()) }}</td>
                    @if ($tab !== 'drafts')
                        <td class="px-4 py-2.5 text-right">{{ $money($invoice->outstanding()) }}</td>
                        <td class="px-4 py-2.5">@if ($invoice->status === \App\Enums\InvoiceStatus::Sent){{ $invoice->daysOverdue() > 0 ? $invoice->daysOverdue().' dagen verlopen' : 'Over '.abs($invoice->daysOverdue()).' dagen' }}@else — @endif</td>
                        <td class="px-4 py-2.5">@if ($action = $invoice->nextAction()){{ $action['label'] }}<div class="text-xs text-slate-500">{{ $action['days'] > 0 ? 'Over '.$action['days'].' dagen' : ($action['days'] === 0 ? 'Vandaag' : abs($action['days']).' dagen geleden') }}</div>@else — @endif</td>
                    @endif
                    <td class="px-4 py-2.5"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">Geen facturen gevonden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($tab === 'drafts' && auth()->user()->can('invoices.manage'))<div class="shrink-0 border-t border-slate-100 px-5 py-3"><button class="rounded-full bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Geselecteerde facturen versturen</button></div>@endif
    <x-pager :paginator="$invoices" />
</form>
</div>
@endsection

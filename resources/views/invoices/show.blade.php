@extends('layouts.app')
@section('title', $invoice->label())
@section('content')
@php
    $money = fn ($value) => \Illuminate\Support\Number::currency($value, 'EUR', 'nl');
    $input = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $manage = auth()->user()->can('invoices.manage');
    $btn = 'rounded-full px-4 py-2 text-sm font-semibold';
    $isSent = $invoice->status === \App\Enums\InvoiceStatus::Sent;
@endphp
<div class="flex flex-col gap-4 p-4 sm:p-5">
    <div>
        <a href="{{ route('invoices.index', ['tab' => $invoice->isDraft() ? 'drafts' : 'outstanding']) }}" class="text-sm text-slate-500 hover:text-slate-800">← Facturen</a>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $invoice->label() }}. {{ $invoice->organization->name }}
            <span class="ml-2 rounded-full px-2.5 py-0.5 align-middle text-xs font-medium {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span>
            @if ($invoice->isCredit())<span class="ml-1 rounded bg-rose-100 px-2 py-0.5 align-middle text-xs text-rose-700">Credit op {{ $invoice->creditFor?->number }}</span>@endif
        </h1>
    </div>
    @if ($errors->any())<div class="rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

    <div class="grid gap-4 rounded-2xl bg-white p-5 text-center shadow-sm ring-1 ring-slate-200 sm:grid-cols-6">
        <div><div class="text-xs text-slate-500">Vervaldatum</div><div class="font-semibold {{ $invoice->isOverdue() ? 'text-rose-600' : '' }}">{{ $invoice->due_date->format('d-m-Y') }}</div><div class="text-xs text-slate-500">@if ($isSent){{ $invoice->daysOverdue() > 0 ? $invoice->daysOverdue().' dagen verlopen' : 'Over '.abs($invoice->daysOverdue()).' dagen' }}@endif</div></div>
        <div><div class="text-xs text-slate-500">Volgende actie</div>@if ($action = $invoice->nextAction())<div class="font-semibold">{{ $action['label'] }}</div><div class="text-xs text-slate-500">{{ $action['days'] > 0 ? 'Over '.$action['days'].' dagen' : ($action['days'] === 0 ? 'Vandaag' : abs($action['days']).' dagen geleden') }}</div>@else<div class="font-semibold">—</div>@endif</div>
        <div><div class="text-xs text-slate-500">Totaal excl. btw</div><div class="font-semibold">{{ $money($invoice->subtotal()) }}</div></div>
        <div><div class="text-xs text-slate-500">Totaal incl. btw</div><div class="font-semibold">{{ $money($invoice->total()) }}</div></div>
        <div><div class="text-xs text-slate-500">Betaald</div><div class="font-semibold">{{ $money($invoice->paidTotal()) }}</div></div>
        <div><div class="text-xs text-slate-500">Openstaand</div><div class="font-semibold">{{ $money($invoice->outstanding()) }}</div></div>
    </div>

    <nav class="flex gap-1 text-sm">
        @foreach (['invoice' => 'Factuur', 'lines' => 'Regels', 'timeline' => 'Tijdlijn'] as $key => $label)
            <a href="{{ route('invoices.show', [$invoice, 'tab' => $key]) }}" class="rounded-full px-4 py-1.5 font-medium {{ $tab === $key ? 'bg-ink text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'invoice')
        <div class="flex flex-wrap items-start gap-2">
            <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="{{ $btn }} bg-sky-600 text-white hover:bg-sky-700">PDF / afdrukken</a>
            @if ($manage && $invoice->isDraft())
                <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="flex flex-wrap items-center gap-3 rounded-2xl bg-white px-4 py-2 shadow-sm ring-1 ring-slate-200">@csrf
                    <label class="text-sm"><input type="radio" name="method" value="print" checked> PDF afdrukken</label>
                    <label class="text-sm"><input type="radio" name="method" value="email"> Verzenden per e-mail</label>
                    <button class="{{ $btn }} bg-emerald-600 text-white hover:bg-emerald-700">Verstuur factuur</button>
                </form>
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('Conceptfactuur verwijderen?')">@csrf @method('DELETE')<button class="{{ $btn }} bg-rose-600 text-white hover:bg-rose-700">Verwijder factuur</button></form>
            @endif
            @if ($manage && ! $invoice->isDraft())
                @if ($isSent)
                    <form method="POST" action="{{ route('invoices.remind', $invoice) }}">@csrf<button class="{{ $btn }} bg-emerald-600 text-white hover:bg-emerald-700">Herinnering versturen</button></form>
                    <form method="POST" action="{{ route('invoices.reminders', $invoice) }}">@csrf @method('PATCH')<button class="{{ $btn }} bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">{{ $invoice->reminder_paused ? 'Herinneringen hervatten' : 'Herinnering pauzeren' }}</button></form>
                @endif
                @unless ($invoice->isCredit())
                    <form method="POST" action="{{ route('invoices.credit', $invoice) }}" onsubmit="return confirm('Creditfactuur aanmaken voor alle regels?')">@csrf<button class="{{ $btn }} bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Creditfactuur maken</button></form>
                @endunless
            @endif
        </div>

        @if ($manage && $isSent)
            <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="grid max-w-3xl gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:grid-cols-4">@csrf
                <h2 class="text-base font-semibold text-brand-700 sm:col-span-4">Betaling registreren</h2>
                <x-field name="amount" label="Bedrag" type="number" step="0.01" :value="$invoice->outstanding()" required />
                <x-field name="paid_on" label="Datum betaling" type="date" :value="today()->format('Y-m-d')" required />
                <x-field name="note" label="Toelichting" class="sm:col-span-2" />
                <div class="sm:col-span-4"><button class="{{ $btn }} bg-emerald-600 text-white hover:bg-emerald-700">Verwerk betaling</button></div>
            </form>
        @endif

        @if ($invoice->payments->isNotEmpty())
            <div class="max-w-3xl rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-2 text-base font-semibold text-brand-700">Betalingen</h2>
                <table class="w-full text-sm">@foreach ($invoice->payments as $payment)<tr><td class="py-1">{{ $payment->paid_on->format('d-m-Y') }}</td><td>{{ $payment->note }}</td><td class="text-right">{{ $money($payment->amount) }}</td></tr>@endforeach</table>
            </div>
        @endif

        @if ($manage && $invoice->isDraft())
            <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="grid max-w-3xl gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2">@csrf @method('PUT')
                <h2 class="text-base font-semibold text-brand-700 sm:col-span-2">Factuurgegevens</h2>
                <x-field name="subject" label="Onderwerp" :value="$invoice->subject" class="sm:col-span-2" />
                <x-field name="invoice_date" label="Factuurdatum" type="date" :value="$invoice->invoice_date->format('Y-m-d')" required />
                <x-field name="due_date" label="Vervaldatum" type="date" :value="$invoice->due_date->format('Y-m-d')" required />
                <label class="text-sm font-medium text-slate-700 sm:col-span-2">Opmerking op factuur<textarea name="notes" rows="3" class="{{ $input }} mt-1">{{ old('notes', $invoice->notes) }}</textarea></label>
                <div><button class="{{ $btn }} bg-brand-700 text-white hover:bg-brand-800">Opslaan</button></div>
            </form>
        @endif

        <div class="max-w-3xl rounded-2xl bg-white p-6 text-sm shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex justify-between">
                <div><div class="font-semibold">{{ $invoice->organization->name }}</div><div>{{ $invoice->organization->visit_street }}</div><div>{{ $invoice->organization->visit_zipcode }} {{ $invoice->organization->visit_city }}</div></div>
                <div class="text-right"><div class="text-lg font-semibold">{{ $invoice->number ? 'Factuur '.$invoice->number : 'Factuur (beschikbaar na versturen)' }}</div><div>Factuurdatum: {{ $invoice->invoice_date->format('d-m-Y') }}</div><div>Vervaldatum: {{ $invoice->due_date->format('d-m-Y') }}</div></div>
            </div>
            @if ($invoice->subject)<div class="mb-3">Onderwerp: {{ $invoice->subject }}</div>@endif
            @include('invoices.partials.lines-table', ['invoice' => $invoice, 'money' => $money])
        </div>
    @elseif ($tab === 'lines')
        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full text-sm">
                <thead class="bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="px-4 py-3">Type</th><th class="px-4 py-3">Omschrijving</th><th class="px-4 py-3">Aantal</th><th class="px-4 py-3">Prijs (€)</th><th class="px-4 py-3">Btw</th><th class="px-4 py-3">Korting %</th><th class="px-4 py-3 text-right">Totaal</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($invoice->lines as $line)
                    @if ($manage && $invoice->isDraft())
                        <tr>
                            <td class="px-4 py-2">{{ $line->typeLabel() }}</td>
                            <td class="px-2 py-1"><input form="line-{{ $line->id }}" name="description" value="{{ $line->description }}" class="{{ $input }}"></td>
                            <td class="px-2 py-1"><input form="line-{{ $line->id }}" type="number" step="0.01" name="quantity" value="{{ $line->quantity }}" class="{{ $input }} w-24"></td>
                            <td class="px-2 py-1"><input form="line-{{ $line->id }}" type="number" step="0.01" name="unit_price" value="{{ $line->unit_price }}" class="{{ $input }} w-28"></td>
                            <td class="px-2 py-1"><select form="line-{{ $line->id }}" name="vat_rate" class="{{ $input }}">@foreach (\App\Models\InvoiceLine::VAT_RATES as $rate => $label)<option value="{{ $rate }}" @selected((float) $line->vat_rate === (float) $rate)>{{ $label }}</option>@endforeach</select></td>
                            <td class="px-2 py-1"><input form="line-{{ $line->id }}" type="number" step="0.01" name="discount_percentage" value="{{ $line->discount_percentage }}" class="{{ $input }} w-20"></td>
                            <td class="px-4 py-2 text-right">{{ $money($line->amount()) }}</td>
                            <td class="whitespace-nowrap px-2 py-1">
                                <button form="line-{{ $line->id }}" class="text-xs font-medium text-brand-700 hover:underline">Opslaan</button>
                                <button form="line-delete-{{ $line->id }}" class="ml-2 text-xs font-medium text-rose-600 hover:underline">Verwijderen</button>
                            </td>
                        </tr>
                    @else
                        <tr><td class="px-4 py-2">{{ $line->typeLabel() }}</td><td class="px-4 py-2">{{ $line->description }}</td><td class="px-4 py-2">{{ $line->quantity }}</td><td class="px-4 py-2">{{ $money($line->unit_price) }}</td><td class="px-4 py-2">{{ (float) $line->vat_rate }}%</td><td class="px-4 py-2">{{ (float) $line->discount_percentage }}%</td><td class="px-4 py-2 text-right">{{ $money($line->amount()) }}</td><td></td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
            @if ($manage && $invoice->isDraft())
                @foreach ($invoice->lines as $line)
                    <form id="line-{{ $line->id }}" method="POST" action="{{ route('invoices.lines.update', [$invoice, $line]) }}" class="hidden">@csrf @method('PUT')</form>
                    <form id="line-delete-{{ $line->id }}" method="POST" action="{{ route('invoices.lines.destroy', [$invoice, $line]) }}" class="hidden">@csrf @method('DELETE')</form>
                @endforeach
                <form method="POST" action="{{ route('invoices.lines.store', $invoice) }}" class="grid gap-2 border-t border-slate-100 p-4 sm:grid-cols-6">@csrf
                    <input name="description" placeholder="Omschrijving" class="{{ $input }} sm:col-span-2" required>
                    <input type="number" step="0.01" name="quantity" value="1" class="{{ $input }}" required>
                    <input type="number" step="0.01" name="unit_price" placeholder="Prijs" class="{{ $input }}" required>
                    <select name="vat_rate" class="{{ $input }}">@foreach (\App\Models\InvoiceLine::VAT_RATES as $rate => $label)<option value="{{ $rate }}">{{ $label }}</option>@endforeach</select>
                    <button class="{{ $btn }} bg-brand-700 text-white hover:bg-brand-800">Regel toevoegen</button>
                </form>
            @endif
        </div>
    @else
        <div class="max-w-3xl rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <ul class="divide-y divide-slate-100 text-sm">@foreach ($invoice->events as $event)<li class="flex justify-between gap-4 py-2"><span>{{ $event->description }}</span><span class="shrink-0 text-slate-500">{{ $event->user?->name ?? 'Systeem' }} · {{ $event->created_at->format('d-m-Y H:i') }}</span></li>@endforeach</ul>
        </div>
    @endif
</div>
@endsection

<!DOCTYPE html>
<html lang="nl"><head><meta charset="utf-8"><title>{{ $invoice->number ? 'Factuur '.$invoice->number : 'Conceptfactuur' }}</title>
<style>body{font-family:system-ui,sans-serif;font-size:14px;max-width:760px;margin:2rem auto;color:#0f172a}table{width:100%;border-collapse:collapse}th,td{padding:.4rem 0}.right{text-align:right}@media print{.noprint{display:none}}</style></head>
<body>
@php($money = fn ($value) => \Illuminate\Support\Number::currency($value, 'EUR', 'nl'))
<p class="noprint"><button onclick="window.print()">Opslaan als PDF / afdrukken</button></p>
<div style="display:flex;justify-content:space-between;margin-bottom:2rem"><div><strong>{{ $invoice->organization->name }}</strong><br>{{ $invoice->organization->visit_street }}<br>{{ $invoice->organization->visit_zipcode }} {{ $invoice->organization->visit_city }}</div>
<div class="right"><h2 style="margin:0">{{ $invoice->number ? 'Factuur '.$invoice->number : 'Factuur (beschikbaar na versturen)' }}</h2>Factuurdatum: {{ $invoice->invoice_date->format('d-m-Y') }}<br>Vervaldatum: {{ $invoice->due_date->format('d-m-Y') }}</div></div>
@if ($invoice->subject)<p>Onderwerp: {{ $invoice->subject }}</p>@endif
@include('invoices.partials.lines-table', ['invoice' => $invoice, 'money' => $money])
</body></html>

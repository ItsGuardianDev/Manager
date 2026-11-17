<table class="w-full text-sm">
    <thead><tr class="border-b border-slate-300 text-left"><th class="py-2">Omschrijving</th><th class="py-2 text-right">Aantal</th><th class="py-2 text-right">Prijs</th><th class="py-2 text-right">Totaal</th></tr></thead>
    <tbody>@foreach ($invoice->lines as $line)<tr class="border-b border-slate-100"><td class="py-2">{{ $line->description }}@if ((float) $line->discount_percentage > 0)<span class="text-slate-500"> (korting {{ (float) $line->discount_percentage }}%)</span>@endif</td><td class="py-2 text-right">{{ number_format($line->quantity, 2, ',', '.') }}</td><td class="py-2 text-right">{{ $money($line->unit_price) }}</td><td class="py-2 text-right">{{ $money($line->amount()) }}</td></tr>@endforeach</tbody>
    <tfoot>
        <tr><td colspan="3" class="pt-3 text-right">Subtotaal</td><td class="pt-3 text-right">{{ $money($invoice->subtotal()) }}</td></tr>
        @foreach ($invoice->vatBreakdown() as $rate => $amount)<tr><td colspan="3" class="text-right">Btw {{ $rate }}%</td><td class="text-right">{{ $money($amount) }}</td></tr>@endforeach
        <tr class="font-semibold"><td colspan="3" class="pt-1 text-right">Totaal</td><td class="pt-1 text-right">{{ $money($invoice->total()) }}</td></tr>
    </tfoot>
</table>
@if ($invoice->notes)<p class="mt-4 whitespace-pre-line">{{ $invoice->notes }}</p>@endif
<p class="mt-4 text-slate-600">We verzoeken je het bovenstaande bedrag van {{ $money($invoice->total()) }} vóór {{ $invoice->due_date->format('d-m-Y') }} te voldoen op onze bankrekening{{ $invoice->number ? ' onder vermelding van factuurnummer '.$invoice->number : '' }}.</p>

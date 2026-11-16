@extends('layouts.app')
@section('title', 'Projectinkoop')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
    <x-project-nav active="purchasing" />
    <div class="flex items-center gap-2 text-sm">
        <form method="GET" class="flex gap-2"><input type="hidden" name="tab" value="{{ $tab }}"><input type="search" name="q" value="{{ request('q') }}" placeholder="Zoeken…" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600"></form>
        @can('projects.edit')<a href="{{ route('purchases.create') }}" class="rounded-full bg-brand-700 px-4 py-2 font-semibold text-white hover:bg-brand-800">+ Nieuw</a>@endcan
    </div>
</div>
<div class="flex shrink-0 items-center gap-6">
    <div class="flex gap-1 text-sm">@foreach (['to_process' => 'Te verwerken', 'processed' => 'Verwerkt'] as $key => $label)<a href="{{ route('purchases.index', ['tab' => $key]) }}" class="rounded-full px-4 py-1.5 font-medium {{ $tab === $key ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>@endforeach</div>
    <div><span class="text-xs text-slate-500">Inkoop totaal</span> <span class="text-lg font-semibold">{{ \Illuminate\Support\Number::currency($total, 'EUR', 'nl') }}</span></div>
</div>
<div class="flex min-h-[20rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="px-4 py-3">Type</th><th class="px-4 py-3">Bron</th><th class="px-4 py-3">Datum</th><th class="px-4 py-3">Omschrijving</th><th class="px-4 py-3">Crediteur</th><th class="px-4 py-3">Project</th><th class="px-4 py-3 text-right">Regels</th><th class="px-4 py-3 text-right">Totaal prijs</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($purchases as $purchase)
                <tr><td class="px-4 py-2.5">{{ $purchase->type === 'expense' ? 'Onkosten' : 'Inkoopfactuur' }}</td><td class="px-4 py-2.5">{{ $purchase->source ?? '—' }}</td><td class="px-4 py-2.5">{{ $purchase->date->format('d-m-Y') }}</td><td class="px-4 py-2.5">{{ $purchase->description }}</td><td class="px-4 py-2.5">{{ $purchase->supplier?->name ?? '—' }}</td>
                    <td class="px-4 py-2.5"><a href="{{ route('projects.show', [$purchase->project, 'tab' => 'costs']) }}" class="text-brand-700 hover:underline">{{ $purchase->project->name }}</a></td><td class="px-4 py-2.5 text-right">{{ $purchase->lines->count() }}</td><td class="px-4 py-2.5 text-right">{{ \Illuminate\Support\Number::currency($purchase->total(), 'EUR', 'nl') }}</td>
                    <td class="px-4 py-2.5 text-right">@can('projects.edit')@if ($purchase->status->value === 'to_process')<form method="POST" action="{{ route('purchases.process', $purchase) }}">@csrf @method('PATCH')<button class="text-xs font-medium text-brand-700 hover:underline">Verwerken</button></form>@endif @endcan</td></tr>
            @empty
                <tr><td colspan="9" class="px-4 py-12 text-center text-slate-500">Klik op 'Nieuw' om een inkoopboeking voor een project aan te maken.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-pager :paginator="$purchases" />
</div>
</div>
@endsection

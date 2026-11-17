@extends('layouts.app')
@section('title', 'Diensten')
@section('content')
@php($money = fn ($v) => \Illuminate\Support\Number::currency((float) $v, 'EUR', 'nl'))
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
    <div class="flex items-baseline gap-3"><h1 class="text-2xl font-semibold tracking-tight">Diensten</h1><p class="hidden text-sm text-slate-500 sm:block">Het aanbod waaruit je diensten aan projecten toevoegt.</p></div>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <form method="GET" class="flex gap-2"><input type="hidden" name="status" value="{{ $status }}"><input type="search" name="q" value="{{ request('q') }}" placeholder="Zoeken op naam…" class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600"></form>
        <details class="relative">
            <summary class="cursor-pointer list-none rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Opties</summary>
            <div class="absolute right-0 z-20 mt-2 w-60 rounded-lg bg-white p-1 shadow-lg ring-1 ring-slate-200">
                @can('projects.edit')
                    <a href="{{ route('services.import') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Diensten importeren</a>
                    <a href="{{ route('services.import.template') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Importsjabloon downloaden</a>
                @endcan
                <a href="{{ route('services.export', [...request()->query(), 'format' => 'csv']) }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Exporteren als CSV</a>
                <a href="{{ route('project-settings.index') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Urensoorten, kostensoorten en omzetgroepen</a>
            </div>
        </details>
        <a href="{{ route('services.export', request()->query()) }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Exporteren naar Excel</a>
        @can('projects.edit')<a href="{{ route('services.create') }}" class="rounded-full bg-brand-700 px-4 py-2 font-semibold text-white hover:bg-brand-800">+ Nieuw</a>@endcan
    </div>
</div>
<div class="flex shrink-0 gap-1 text-sm">
    @foreach (['available' => 'Beschikbaar ('.$counts['available'].')', 'blocked' => 'Geblokkeerd ('.$counts['blocked'].')', 'all' => 'Alle'] as $key => $label)
        <a href="{{ route('services.index', ['status' => $key, 'q' => request('q')]) }}" class="rounded-full px-4 py-1.5 font-medium {{ $status === $key ? 'bg-ink text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
    @endforeach
</div>
@if (session('status'))<div class="shrink-0 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ session('status') }}</div>@endif
<div class="flex min-h-[20rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="px-4 py-3">Dienst</th><th class="px-4 py-3">Factuurmethode</th><th class="px-4 py-3 text-right">Prijs</th><th class="px-4 py-3">Omzetgroep</th><th class="px-4 py-3">Registreren</th><th class="px-4 py-3 text-right">Standaard uren</th><th class="px-4 py-3 text-right">In projecten</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($services as $service)
                <tr class="{{ $service->is_blocked ? 'text-slate-400' : '' }}">
                    <td class="px-4 py-2.5"><a href="{{ route('services.edit', $service) }}" class="font-medium {{ $service->is_blocked ? 'text-slate-500' : 'text-brand-700' }} hover:underline">{{ $service->name }}</a>@if ($service->is_blocked)<span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Geblokkeerd</span>@endif</td>
                    <td class="px-4 py-2.5">{{ $service->invoice_method?->label() ?? '—' }}@if ($service->billing_cycle)<div class="text-xs text-slate-500">{{ $service->billing_cycle->label() }}</div>@endif</td>
                    <td class="px-4 py-2.5 text-right">{{ $service->price !== null ? $money($service->price) : '—' }}</td>
                    <td class="px-4 py-2.5">{{ $service->revenueGroup?->name ?? '—' }}</td>
                    <td class="px-4 py-2.5"><div class="flex gap-1 text-xs">@foreach ([['Uren', $service->allow_hours], ['Kosten', $service->allow_costs], ['Planning', $service->is_plannable]] as [$label, $on])<span class="rounded-full px-2 py-0.5 {{ $on ? 'bg-brand-50 text-brand-800' : 'bg-slate-100 text-slate-400 line-through' }}">{{ $label }}</span>@endforeach</div></td>
                    <td class="px-4 py-2.5 text-right">{{ rtrim(rtrim(number_format($service->budgetHours(), 2, ',', '.'), '0'), ',') ?: '0' }}</td>
                    <td class="px-4 py-2.5 text-right">{{ $service->project_services_count }}</td>
                    <td class="px-4 py-2.5 text-right">@can('projects.edit')<form method="POST" action="{{ route('services.toggle-blocked', $service) }}">@csrf @method('PATCH')<button class="text-xs font-medium text-brand-700 hover:underline">{{ $service->is_blocked ? 'Deblokkeren' : 'Blokkeren' }}</button></form>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">Geen diensten gevonden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-pager :paginator="$services" />
</div>
</div>
@endsection

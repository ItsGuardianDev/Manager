@extends('layouts.app')
@section('title', $organization->name)
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
@php
    $address = fn ($p) => collect([$organization->{$p.'_street'}, trim($organization->{$p.'_zipcode'}.' '.$organization->{$p.'_city'}), $organization->{$p.'_country'}])->filter()->implode(', ');
    $row = fn ($label, $value) => $value ? '<div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">'.e($label).'</dt><dd class="text-right font-medium">'.e($value).'</dd></div>' : '';
@endphp
<div class="flex shrink-0 flex-wrap items-start justify-between gap-4">
    <div>
        <a href="{{ route('organizations.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Bedrijven</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">{{ $organization->name }}</h1>
            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $organization->type->badgeClasses() }}">{{ $organization->type->label() }}</span>
            <x-status-badge :active="$organization->is_active" />
        </div>
        <p class="mt-1 text-sm text-slate-500">{{ collect([$organization->industry?->name, $organization->companySize ? $organization->companySize->name : null])->filter()->implode(' · ') }}</p>
    </div>
    <div class="flex gap-2 text-sm">
        <a href="{{ route('organizations.edit', $organization) }}" class="rounded-lg bg-brand-700 px-3.5 py-2 font-semibold text-white hover:bg-brand-800">Bewerken</a>
        <form method="POST" action="{{ route('organizations.toggle-active', $organization) }}" @if ($organization->is_active) onsubmit="return confirm('Dit bedrijf deactiveren?')" @endif>
            @csrf @method('PATCH')
            <button class="rounded-lg px-3.5 py-2 font-medium ring-1 ring-inset {{ $organization->is_active ? 'text-rose-700 ring-rose-300 hover:bg-rose-50' : 'text-emerald-700 ring-emerald-300 hover:bg-emerald-50' }}">{{ $organization->is_active ? 'Deactiveren' : 'Activeren' }}</button>
        </form>
    </div>
</div>

<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-3 lg:grid-rows-2">
    <x-card title="Gegevens" class="lg:col-start-1 lg:row-start-1">
        <dl class="divide-y divide-slate-100 text-sm">
            {!! $row('Verantwoordelijke', $organization->relationManager?->name) !!}
            {!! $row('E-mail', $organization->email) !!}
            {!! $row('Telefoon', $organization->phone) !!}
            {!! $row('Website', $organization->website) !!}
            {!! $row('LinkedIn', $organization->linkedin_url) !!}
            {!! $row('KvK', $organization->coc_number) !!}
            {!! $row('Btw', $organization->vat_number) !!}
            {!! $row('Bezoekadres', $address('visit')) !!}
            {!! $row('Postadres', $address('postal')) !!}
        </dl>
    </x-card>

    <x-card title="Facturatie" class="lg:col-start-1 lg:row-start-2">
        <dl class="divide-y divide-slate-100 text-sm">
            {!! $row('Factuurnaam', $organization->billing_name) !!}
            {!! $row('T.a.v.', $organization->billing_attention) !!}
            {!! $row('Factuur e-mail', $organization->billing_email) !!}
            {!! $row('Factuuradres', $address('billing')) !!}
            {!! $row('IBAN', $organization->iban) !!}
            {!! $row('BIC', $organization->bic) !!}
            {!! $row('Debiteurnummer', $organization->debtor_number) !!}
            {!! $row('Referentie', $organization->invoice_reference) !!}
            {!! $row('Verstrekkingswijze', $organization->invoice_delivery?->label()) !!}
            {!! $row('Betalingstermijn', $organization->payment_term_days !== null ? $organization->payment_term_days.' dagen' : null) !!}
        </dl>
    </x-card>

    <x-card title="Personen ({{ $organization->people->count() }})" class="lg:col-span-2 lg:col-start-2 lg:row-start-1">
        <x-slot:actions><a href="{{ route('people.create', ['organization_id' => $organization->id]) }}" class="text-sm font-medium text-brand-700 hover:underline">Nieuwe persoon</a></x-slot:actions>
        @if ($organization->people->isNotEmpty())
            <ul class="divide-y divide-slate-100">
                @foreach ($organization->people as $person)
                    <li class="py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <a href="{{ route('people.show', $person) }}" class="font-medium text-brand-700 hover:underline">{{ $person->full_name }}</a>
                                @if ($person->pivot->is_primary)<span class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-700">Primair</span>@endif
                                @unless ($person->is_active)<span class="ml-1 text-xs text-slate-500">(inactief)</span>@endunless
                                <p class="text-sm text-slate-500">{{ $person->email }}</p>
                            </div>
                            <form method="POST" action="{{ route('links.destroy', [$organization, $person]) }}" onsubmit="return confirm('Deze persoon ontkoppelen?')">
                                @csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Ontkoppelen</button>
                            </form>
                        </div>
                        <form method="POST" action="{{ route('links.update', [$organization, $person]) }}" class="mt-2 flex flex-wrap items-center gap-2">
                            @csrf @method('PATCH')
                            <input name="job_title" value="{{ $person->pivot->job_title }}" placeholder="Functie" class="w-44 rounded-lg border-0 px-2.5 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                            <input name="department" value="{{ $person->pivot->department }}" placeholder="Afdeling" class="w-36 rounded-lg border-0 px-2.5 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                            <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" name="is_primary" value="1" @checked($person->pivot->is_primary) class="rounded border-slate-300 text-brand-600"> Primair</label>
                            <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Opslaan</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-slate-500">Nog geen personen gekoppeld.</p>
        @endif

        @if ($availablePeople->isNotEmpty())
            <form method="POST" action="{{ route('links.store') }}" class="mt-5 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4">
                @csrf
                <input type="hidden" name="organization_id" value="{{ $organization->id }}">
                <x-select name="person_id" label="Bestaande persoon koppelen" placeholder="Kies een persoon…" :options="$availablePeople->mapWithKeys(fn ($p) => [$p->id => $p->full_name])" class="min-w-52 flex-1" />
                <x-field name="job_title" label="Functie" :keep="false" class="w-44" />
                <button class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-700">Koppelen</button>
            </form>
        @endif
    </x-card>


    <x-notes class="lg:col-span-2 lg:col-start-2 lg:row-start-2" :notes="$organization->notes" :action="route('organizations.notes.store', $organization)" />
</div>
</div>
@endsection

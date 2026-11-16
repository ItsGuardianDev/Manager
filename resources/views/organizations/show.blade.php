@extends('layouts.app')
@section('title', $organization->name)
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
@php
    $address = fn ($p) => collect([$organization->{$p.'_street'}, trim($organization->{$p.'_zipcode'}.' '.$organization->{$p.'_city'}), $organization->{$p.'_country'}])->filter()->implode(', ');
    $row = fn ($label, $value) => $value ? '<div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">'.e($label).'</dt><dd class="break-all text-right font-medium">'.e($value).'</dd></div>' : '';
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
        <a href="{{ route('organizations.edit', $organization) }}" class="rounded-lg bg-brand-700 px-3.5 py-2 font-semibold text-white hover:bg-brand-800">Bedrijfsgegevens bewerken</a>
        <form method="POST" action="{{ route('organizations.toggle-active', $organization) }}" @if ($organization->is_active) onsubmit="return confirm('Dit bedrijf deactiveren?')" @endif>
            @csrf @method('PATCH')
            <button class="rounded-lg px-3.5 py-2 font-medium ring-1 ring-inset {{ $organization->is_active ? 'text-rose-700 ring-rose-300 hover:bg-rose-50' : 'text-emerald-700 ring-emerald-300 hover:bg-emerald-50' }}">{{ $organization->is_active ? 'Deactiveren' : 'Activeren' }}</button>
        </form>
    </div>
</div>

<div data-tabs data-initial="{{ $tab }}" class="flex min-h-0 flex-col lg:flex-1">
    <div class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 text-sm font-medium" role="tablist">
        @foreach (['overview' => 'Overzicht', 'details' => 'Bedrijfsgegevens', 'people' => 'Contactpersonen bij dit bedrijf', 'billing' => 'Facturatie', 'documents' => 'Documenten'] as $key => $label)
            <button type="button" data-tab-target="{{ $key }}" class="-mb-px whitespace-nowrap border-b-2 border-transparent px-4 py-2.5 text-slate-500 hover:text-slate-800">{{ $label }}</button>
        @endforeach
    </div>
    <div class="min-h-0 flex-1 pt-4">
        <div data-tab-panel="overview" class="grid gap-4 lg:h-full lg:grid-cols-[20rem_1fr_18rem]">
            <div class="space-y-4 lg:overflow-y-auto lg:pr-1">
                <x-card title="Algemeen" class="overflow-visible">
                    <dl class="divide-y divide-slate-100 text-sm">
                        {!! $row('Relatiebeheerder', $organization->relationManager?->name) !!}
                        {!! $row('Team', $organization->team?->name) !!}
                        {!! $row('Klantgroep', $organization->customerGroup?->name) !!}
                        {!! $row('Branche', $organization->industry?->name) !!}
                        {!! $row('Bedrijfsgrootte', $organization->companySize?->name) !!}
                        {!! $row('Website', $organization->website) !!}
                        {!! $row('E-mailadres', $organization->email) !!}
                        {!! $row('Telefoonnummer', $organization->phone) !!}
                        {!! $row('LinkedIn', $organization->linkedin_url) !!}
                        {!! $row('Aangemaakt op', $organization->created_at->format('d-m-Y')) !!}
                    </dl>
                </x-card>
                <x-card title="Adresgegevens" class="overflow-visible">
                    <p class="text-sm">{{ $address('visit') ?: '—' }}</p>
                    @if ($organization->hasDifferentPostalAddress())<p class="mt-2 text-xs text-slate-500">Postadres</p><p class="text-sm">{{ $address('postal') }}</p>@endif
                </x-card>
            </div>
            <x-timeline :notes="$organization->notes" :action="route('organizations.notes.store', $organization)" class="mx-auto w-full max-w-3xl lg:h-full" />
            <div class="space-y-4 lg:overflow-y-auto">
                <x-card title="Contactpersonen ({{ $organization->people->count() }})" class="overflow-visible">
                    @forelse ($organization->people as $person)
                        <div class="mb-2 rounded-lg bg-slate-50 p-3 text-sm">
                            <a href="{{ route('people.show', $person) }}" class="font-medium text-brand-700 hover:underline">{{ $person->full_name }}</a>
                            <div class="text-xs text-slate-500">{{ $person->pivot->job_title }}</div>
                            @if ($person->email)<div class="break-all text-xs">{{ $person->email }}</div>@endif
                            @if ($person->phone || $person->mobile)<div class="text-xs">{{ $person->mobile ?: $person->phone }}</div>@endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nog geen contactpersonen.</p>
                    @endforelse
                </x-card>
            </div>
        </div>

        <div data-tab-panel="details" class="grid gap-4 lg:h-full lg:grid-cols-2 lg:content-start">
            <x-card title="Algemene gegevens" class="overflow-visible">
                <dl class="divide-y divide-slate-100 text-sm">
                    {!! $row('Relatiesoort', $organization->type->label()) !!}
                    {!! $row('Naam bedrijf', $organization->name) !!}
                    {!! $row('Nummer KvK', $organization->coc_number) !!}
                    {!! $row('E-mail', $organization->email) !!}
                    {!! $row('Telefoon', $organization->phone) !!}
                    {!! $row('Website', $organization->website) !!}
                    {!! $row('Linkedin url', $organization->linkedin_url) !!}
                    {!! $row('Actief', $organization->is_active ? 'Ja' : 'Nee') !!}
                </dl>
            </x-card>
            <x-card title="Adressen" class="overflow-visible">
                <dl class="divide-y divide-slate-100 text-sm">
                    {!! $row('Bezoekadres', $address('visit')) !!}
                    {!! $row('Postadres', $organization->hasDifferentPostalAddress() ? $address('postal') : 'Gelijk aan bezoekadres') !!}
                </dl>
            </x-card>
        </div>

        <div data-tab-panel="people" class="lg:h-full">
    <x-card title="Personen ({{ $organization->people->count() }})">
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



        </div>

        <div data-tab-panel="billing" class="grid gap-4 lg:h-full lg:grid-cols-2 lg:content-start">
            <x-card title="Voorkeursinstellingen voor de facturatie" class="overflow-visible">
                <dl class="divide-y divide-slate-100 text-sm">
                    {!! $row('Factuur t.a.v.', $organization->invoice_attention->label().($organization->invoiceContactPerson && $organization->invoice_attention === \App\Enums\InvoiceAttention::ContactPerson ? ': '.$organization->invoiceContactPerson->full_name : '')) !!}
                    {!! $row('Factuurverstrekking', $organization->invoice_delivery?->label()) !!}
                    {!! $row('Betaaltermijn', $organization->payment_term_days !== null ? (\App\Models\Organization::PAYMENT_TERMS[$organization->payment_term_days] ?? $organization->payment_term_days.' dagen') : null) !!}
                    {!! $row('Standaard btw-code', \App\Models\Organization::VAT_CODES[$organization->default_vat_code] ?? null) !!}
                    {!! $row('Btw-nummer', $organization->vat_number) !!}
                    {!! $row('Is vrijgesteld van btw', $organization->is_vat_exempt ? 'Ja' : null) !!}
                    {!! $row('IBAN nummer', $organization->iban) !!}
                    {!! $row('BIC code', $organization->bic) !!}
                    {!! $row('Factuurjabloon', \App\Models\Organization::INVOICE_TEMPLATES[$organization->invoice_template] ?? null) !!}
                    {!! $row('Herinneringsplan', \App\Models\Organization::REMINDER_PLANS[$organization->reminder_plan] ?? null) !!}
                    {!! $row('E-mail herinneringen', $organization->reminder_email) !!}
                </dl>
                <a href="{{ route('organizations.edit', $organization) }}" class="mt-4 inline-block text-sm font-medium text-brand-700 hover:underline">Voorkeuren wijzigen</a>
            </x-card>
            <x-card title="Facturen"><p class="text-sm text-slate-500">Er zijn nog geen facturen. Facturen verschijnen hier zodra de factuurmodule beschikbaar is.</p></x-card>
        </div>

        <x-documents data-tab-panel="documents" class="lg:h-full" :documents="$organization->documents" :action="route('organizations.documents.store', $organization)" />
    </div>
</div>
</div>
@endsection

@extends('layouts.app')
@php($editing = $organization->exists)
@section('title', $editing ? 'Bewerk '.$organization->name : 'Nieuw bedrijf')
@section('content')
<form method="POST" action="{{ $editing ? route('organizations.update', $organization) : route('organizations.store') }}" class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="flex shrink-0 items-center justify-between">
        <div>
            <a href="{{ $editing ? route('organizations.show', $organization) : route('organizations.index') }}" class="text-sm text-slate-500 hover:text-ink">← Terug</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $editing ? 'Bedrijf bewerken' : 'Nieuw bedrijf' }}</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ $editing ? route('organizations.show', $organization) : route('organizations.index') }}" class="rounded-full px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white">Annuleren</a>
            <button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ $editing ? 'Wijzigingen opslaan' : 'Bedrijf aanmaken' }}</button>
        </div>
    </div>

    <div class="min-h-0 overflow-y-auto rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:flex-1">
        <div class="grid gap-x-16 gap-y-8 lg:grid-cols-2 lg:gap-x-10">
            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-brand-700">Algemene gegevens</h2>
                <x-select inline name="type" label="Relatiesoort" :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])" :selected="$organization->type?->value" />
                <x-field inline name="name" label="Naam bedrijf" :value="$organization->name" required />
                <x-field inline name="coc_number" label="Nummer KvK" :value="$organization->coc_number" />
                <x-field inline name="email" label="E-mail" type="email" :value="$organization->email" />
                <x-field inline name="phone" label="Telefoon" :value="$organization->phone" />
                <x-field inline name="website" label="Website" :value="$organization->website" />
                <x-field inline name="linkedin_url" label="Linkedin url" :value="$organization->linkedin_url" />
                @unless ($editing)
                    <div class="grid grid-cols-[8.5rem_1fr] items-start gap-x-4">
                        <label for="note" class="pt-2 text-sm font-medium text-slate-700">Notitie</label>
                        <div>
                            <textarea id="note" name="note" rows="5" class="block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset {{ $errors->has('note') ? 'ring-rose-400' : 'ring-slate-300' }} focus:ring-2 focus:ring-inset focus:ring-brand-600">{{ old('note') }}</textarea>
                            @error('note')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endunless
                <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4">
                    <span class="text-sm font-medium text-slate-700">Actief</span>
                    <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $organization->is_active)) class="rounded border-slate-300 text-brand-600"></label>
                </div>

                <h2 class="pt-4 text-lg font-semibold text-brand-700">Extra gegevens</h2>
                <x-select inline name="relation_manager_id" label="Relatiebeheerder" placeholder="" :options="$employees->pluck('name', 'id')" :selected="$organization->relation_manager_id" />
                <x-select inline name="industry_id" label="Branche" placeholder="" :options="$industries->pluck('name', 'id')" :selected="$organization->industry_id" />
                <x-select inline name="company_size_id" label="Bedrijfsgrootte" placeholder="" :options="$sizes->pluck('name', 'id')" :selected="$organization->company_size_id" />
                <x-select inline name="team_id" label="Team" placeholder="" :options="$teams" :selected="$organization->team_id" />
                <x-select inline name="customer_group_id" label="Klantgroep" placeholder="" :options="$customerGroups" :selected="$organization->customer_group_id" />
            </div>

            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-brand-700">Bezoekadres</h2>
                <x-select inline name="visit_country" label="Land" :options="collect(array_unique([...App\Models\Organization::COUNTRIES, ...array_filter([$organization->{'visit_country'}])]))->mapWithKeys(fn ($c) => [$c => $c])" :selected="$organization->visit_country" />
                <x-field inline name="visit_street" label="Adres" :value="$organization->visit_street" />
                <x-field inline name="visit_zipcode" label="Postcode" :value="$organization->visit_zipcode" />
                <x-field inline name="visit_city" label="Plaatsnaam" :value="$organization->visit_city" />

                <h2 class="pt-4 text-lg font-semibold text-brand-700">Postadres</h2>
                <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4">
                    <label for="postal_differs" class="text-sm font-medium text-slate-700">Ongelijk aan bezoekadres</label>
                    <label><input type="checkbox" id="postal_differs" name="postal_differs" value="1" data-reveal="postal-fields" @checked(old('postal_differs', $organization->hasDifferentPostalAddress())) class="rounded border-slate-300 text-brand-600"></label>
                </div>
                <div id="postal-fields" class="space-y-3" hidden>
                    <x-select inline name="postal_country" label="Land" :options="collect(array_unique([...App\Models\Organization::COUNTRIES, ...array_filter([$organization->{'postal_country'}])]))->mapWithKeys(fn ($c) => [$c => $c])" :selected="$organization->postal_country ?? 'Nederland'" />
                    <x-field inline name="postal_street" label="Adres" :value="$organization->postal_street" />
                    <x-field inline name="postal_zipcode" label="Postcode" :value="$organization->postal_zipcode" />
                    <x-field inline name="postal_city" label="Plaatsnaam" :value="$organization->postal_city" />
                </div>

                <h2 class="pt-4 text-lg font-semibold text-brand-700">Facturatievoorkeuren</h2>
                <x-select inline name="invoice_attention" label="Factuur t.a.v." :options="collect(App\Enums\InvoiceAttention::cases())->mapWithKeys(fn ($a) => [$a->value => $a->label()])" :selected="$organization->invoice_attention?->value ?? 'none'" />
                <x-select inline name="invoice_contact_person_id" label="Contactpersoon" placeholder="" :options="$contactPeople" :selected="$organization->invoice_contact_person_id" />
                <x-select inline name="invoice_delivery" label="Factuurverstrekking" placeholder="" :options="collect(App\Enums\InvoiceDelivery::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()])" :selected="$organization->invoice_delivery?->value" />
                <x-select inline name="payment_term_days" label="Betaaltermijn" placeholder="" :options="App\Models\Organization::PAYMENT_TERMS" :selected="$organization->payment_term_days" />
                <x-select inline name="default_vat_code" label="Standaard btw-code" placeholder="(geen standaard btw-code)" :options="App\Models\Organization::VAT_CODES" :selected="$organization->default_vat_code" />
                <x-field inline name="vat_number" label="Btw-nummer" :value="$organization->vat_number" />
                <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4">
                    <span class="text-sm font-medium text-slate-700">Is vrijgesteld van btw</span>
                    <label><input type="checkbox" name="is_vat_exempt" value="1" @checked(old('is_vat_exempt', $organization->is_vat_exempt)) class="rounded border-slate-300 text-brand-600"></label>
                </div>
                <x-field inline name="iban" label="IBAN nummer" :value="$organization->iban" />
                <x-field inline name="bic" label="BIC code" :value="$organization->bic" />
                <x-select inline name="invoice_template" label="Factuurjabloon" placeholder="" :options="App\Models\Organization::INVOICE_TEMPLATES" :selected="$organization->invoice_template" />
                <x-select inline name="reminder_plan" label="Herinneringsplan" placeholder="" :options="App\Models\Organization::REMINDER_PLANS" :selected="$organization->reminder_plan" />
                <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4">
                    <label for="use_reminder_email" class="text-sm font-medium text-slate-700">Afwijkend e-mailadres voor herinneringen</label>
                    <label><input type="checkbox" id="use_reminder_email" name="use_reminder_email" value="1" data-reveal="reminder-email-field" @checked(old('use_reminder_email', filled($organization->reminder_email))) class="rounded border-slate-300 text-brand-600"></label>
                </div>
                <div id="reminder-email-field"><x-field inline name="reminder_email" label="E-mail herinneringen" type="email" :value="$organization->reminder_email" /></div>
            </div>
        </div>
    </div>
</form>
@endsection

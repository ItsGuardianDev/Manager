@extends('layouts.app')
@php($editing = $person->exists)
@section('title', $editing ? 'Bewerk '.$person->full_name : 'Nieuwe persoon')
@section('content')
<form method="POST" action="{{ $editing ? route('people.update', $person) : route('people.store') }}" class="flex min-h-full flex-col gap-4 p-4 sm:p-5 lg:h-full">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="flex shrink-0 items-center justify-between">
        <div>
            <a href="{{ $editing ? route('people.show', $person) : route('people.index') }}" class="text-sm text-slate-500 hover:text-ink">← Terug</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $editing ? 'Persoonsgegevens' : 'Nieuwe persoon' }}</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ $editing ? route('people.show', $person) : route('people.index') }}" class="rounded-full px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white">Annuleren</a>
            <button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ $editing ? 'Opslaan' : 'Persoon aanmaken' }}</button>
        </div>
    </div>

    @if ($editing && $person->employee)
        <div class="shrink-0 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">Deze persoon is een medewerker. Naam en werkgegevens worden beheerd bij Medewerkers en bij het opslaan van de medewerker weer overschreven.</div>
    @endif

    @if ($errors->has('last_name') && session('duplicate_person_id'))
        <div class="shrink-0 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p>{{ $errors->first('last_name') }}
                <a class="font-medium underline" href="{{ route('people.show', session('duplicate_person_id')) }}" target="_blank">Bekijk bestaand record</a>.</p>
            <label class="mt-2 flex items-center gap-2"><input type="checkbox" name="confirm_duplicate" value="1" class="rounded border-amber-400 text-brand-600"> Dit is een andere persoon, toch opslaan</label>
        </div>
    @endif

    <div class="grid min-h-0 gap-x-10 gap-y-6 overflow-y-auto rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:flex-1 lg:grid-cols-2 lg:content-start">
        <div class="space-y-4">
            <h2 class="text-base font-semibold text-brand-700">Algemene gegevens</h2>
            <x-select inline name="gender" label="Geslacht" placeholder="—" :options="['male' => 'Man', 'female' => 'Vrouw', 'other' => 'Anders']" :selected="$person->gender" />
            <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4">
                <label for="use_salutation" class="text-sm font-medium text-slate-700">Afwijkende aanspreekvorm gebruiken</label>
                <input type="checkbox" id="use_salutation" name="use_salutation" value="1" data-reveal="salutation-field" @checked(old('use_salutation', filled($person->salutation))) class="justify-self-start rounded border-slate-300 text-brand-600">
            </div>
            <div id="salutation-field"><x-field inline name="salutation" label="Aanspreekvorm" :value="$person->salutation" placeholder="Bijv. Beste Jan," /></div>
            <x-field inline name="first_name" label="Voornaam" :value="$person->first_name" />
            <x-field inline name="initials" label="Initialen" :value="$person->initials" />
            <x-field inline name="last_name_prefix" label="Tussenvoegsel" :value="$person->last_name_prefix" hint="bijv. van der" />
            <x-field inline name="last_name" label="Achternaam" :value="$person->last_name" required />
            <x-field inline name="date_of_birth" label="Geboortedatum" type="date" :value="$person->date_of_birth?->format('Y-m-d')" />
            @unless ($editing)
                <div class="grid grid-cols-[8.5rem_1fr] items-start gap-x-4"><label for="note" class="pt-2 text-sm font-medium text-slate-700">Notitie</label>
                    <textarea id="note" name="note" rows="4" class="block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600">{{ old('note') }}</textarea></div>
            @endunless
            <div class="grid grid-cols-[8.5rem_1fr] items-center gap-x-4"><span class="text-sm font-medium text-slate-700">Actief</span>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $person->is_active)) class="rounded border-slate-300 text-brand-600"></label></div>

            <h2 class="pt-4 text-base font-semibold text-brand-700">Extra gegevens</h2>
            <x-select inline name="relation_manager_id" label="Relatiebeheerder" placeholder="— Geen —" :options="$managers" :selected="$person->relation_manager_id" />
            <x-select inline name="team_id" label="Team" placeholder="— Geen —" :options="$teams" :selected="$person->team_id" />
            @unless ($editing)
                <x-select inline name="organization_id" label="Contactpersoon bij" placeholder="— Geen —" :options="$organizations->pluck('name', 'id')" :selected="$preselectedOrganizationId" />
                <x-field inline name="job_title" label="Functie" :value="null" />
            @endunless
        </div>
        <div class="space-y-4">
            <h2 class="text-base font-semibold text-brand-700">Adres</h2>
            <x-select inline name="country" label="Land" placeholder="" :options="collect(array_unique([...App\Models\Organization::COUNTRIES, ...array_filter([$person->country])]))->mapWithKeys(fn ($c) => [$c => $c])" :selected="$person->country ?? 'Nederland'" />
            <x-field inline name="street" label="Adres" :value="$person->street" />
            <x-field inline name="zipcode" label="Postcode" :value="$person->zipcode" />
            <x-field inline name="city" label="Plaatsnaam" :value="$person->city" />
            <h2 class="pt-4 text-base font-semibold text-brand-700">Contactgegevens</h2>
            <x-field inline name="email" label="E-mail" type="email" :value="$person->email" />
            <x-field inline name="phone" label="Telefoon" :value="$person->phone" />
            <x-field inline name="mobile" label="Mobiel" :value="$person->mobile" />
            <x-field inline name="twitter_url" label="Twitter url" :value="$person->twitter_url" />
            <x-field inline name="linkedin_url" label="Linkedin url" :value="$person->linkedin_url" />
            <x-field inline name="facebook_url" label="Facebook url" :value="$person->facebook_url" />
        </div>
    </div>
</form>
@endsection

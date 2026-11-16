@extends('layouts.app')
@php($editing = $person->exists)
@section('title', $editing ? 'Bewerk '.$person->full_name : 'Nieuwe persoon')
@section('content')
<form method="POST" action="{{ $editing ? route('people.update', $person) : route('people.store') }}" class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="flex shrink-0 items-center justify-between">
        <div>
            <a href="{{ $editing ? route('people.show', $person) : route('people.index') }}" class="text-sm text-slate-500 hover:text-ink">← Terug</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $editing ? 'Persoon bewerken' : 'Nieuwe persoon' }}</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ $editing ? route('people.show', $person) : route('people.index') }}" class="rounded-full px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white">Annuleren</a>
            <button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ $editing ? 'Wijzigingen opslaan' : 'Persoon aanmaken' }}</button>
        </div>
    </div>

    @if ($errors->has('last_name') && session('duplicate_person_id'))
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p>{{ $errors->first('last_name') }}
                <a class="font-medium underline" href="{{ route('people.show', session('duplicate_person_id')) }}" target="_blank">Bekijk bestaand record</a>.</p>
            <label class="mt-2 flex items-center gap-2"><input type="checkbox" name="confirm_duplicate" value="1" class="rounded border-amber-400 text-brand-600"> Dit is een andere persoon, toch opslaan</label>
        </div>
    @endif

    <div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-3 lg:grid-rows-2">
    <x-card title="Persoonsgegevens" class="lg:row-span-2">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
            <x-field name="first_name" label="Voornaam" :value="$person->first_name" />
            <x-field name="initials" label="Voorletters" :value="$person->initials" />
            <x-field name="last_name_prefix" label="Tussenvoegsel" :value="$person->last_name_prefix" hint="bijv. van der" />
            <x-field name="last_name" label="Achternaam" :value="$person->last_name" required />
            <x-select name="gender" label="Geslacht" placeholder="—" :options="['male' => 'Man', 'female' => 'Vrouw', 'other' => 'Anders']" :selected="$person->gender" />
            <x-field name="date_of_birth" label="Geboortedatum" type="date" :value="$person->date_of_birth?->format('Y-m-d')" />
            <x-field name="linkedin_url" label="LinkedIn" :value="$person->linkedin_url" class="lg:col-span-2" />
        </div>
    </x-card>


    <x-card title="Contact" class="lg:col-start-2 lg:row-start-1">
        <div class="grid gap-4 sm:grid-cols-1">
            <x-field name="email" label="E-mail" type="email" :value="$person->email" />
            <x-field name="phone" label="Telefoon" :value="$person->phone" />
            <x-field name="mobile" label="Mobiel" :value="$person->mobile" />
        </div>
    </x-card>


    <x-card title="Adres" class="lg:col-start-2 lg:row-start-2">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
            <x-field name="street" label="Straat en huisnummer" :value="$person->street" class="sm:col-span-2" />
            <x-field name="zipcode" label="Postcode" :value="$person->zipcode" />
            <x-field name="city" label="Plaats" :value="$person->city" />
            <x-field name="country" label="Land" :value="$person->country" />
        </div>
    </x-card>


    @unless ($editing)
        <x-card title="Bedrijf" class="lg:col-start-3 lg:row-start-1">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="organization_id" label="Koppelen aan bedrijf" placeholder="— Geen —" :options="$organizations->pluck('name', 'id')" :selected="$preselectedOrganizationId" />
                <x-field name="job_title" label="Functie" :value="null" />
            </div>
            <p class="mt-2 text-xs text-slate-500">Meer bedrijven koppel je vanuit het profiel van de persoon.</p>
        </x-card>
    @endunless
    <x-card title="Status" class="lg:col-start-3 lg:row-start-2">
        <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $person->is_active)) class="rounded border-slate-300 text-brand-600"> Actief
    </label>
    </x-card>
    </div>
</form>
@endsection

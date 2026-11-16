@extends('layouts.app')
@section('title', $employee->exists ? $employee->full_name.' bewerken' : 'Nieuwe medewerker')
@section('content')
@php($canPrivate = auth()->user()->can('updatePrivate', $employee))
<form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="flex min-h-full flex-col gap-4 p-4 sm:p-5 lg:h-full">
    @csrf
    @if ($employee->exists) @method('PUT') @endif
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $employee->exists ? $employee->full_name : 'Medewerkers' }}</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $employee->exists ? 'Medewerker bewerken' : 'Nieuwe medewerker' }}</h1>
        </div>
        <button class="rounded-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">Opslaan</button>
    </div>

    <div class="grid min-h-0 gap-4 overflow-y-auto lg:flex-1 lg:grid-cols-2 lg:content-start">
        <x-card title="Algemene gegevens" class="lg:overflow-visible">
            <div class="space-y-4">
                <x-field name="first_name" label="Voornaam" :value="$employee->first_name" required inline />
                <x-field name="initials" label="Voorletters" :value="$employee->initials" inline />
                <x-field name="last_name_prefix" label="Tussenvoegsel" :value="$employee->last_name_prefix" inline />
                <x-field name="last_name" label="Achternaam" :value="$employee->last_name" required inline />
                <x-field name="email" label="Werk e-mail" type="email" :value="$employee->email" required inline />
                <x-field name="phone" label="Telefoon" :value="$employee->phone" inline />
                <x-field name="mobile" label="Mobiel" :value="$employee->mobile" inline />
                <x-field name="employee_number" label="Personeelsnummer" :value="$employee->employee_number" hint="Leeg laten voor automatisch nummer." inline />
            </div>
        </x-card>

        <x-card title="Functie en organisatie">
            <div class="space-y-4">
                <x-field name="job_title" label="Functie" :value="$employee->job_title" inline />
                <x-select name="team_id" label="Team" placeholder="— Geen —" :options="$teams" :selected="$employee->team_id" inline />
                <x-select name="manager_id" label="Leidinggevende" placeholder="— Geen —" :options="$managers" :selected="$employee->manager_id" inline />
                <x-select name="discipline_id" label="Discipline" placeholder="— Geen —" :options="$disciplines" :selected="$employee->discipline_id" inline />
                <x-field name="hired_at" label="Datum in dienst" type="date" :value="$employee->hired_at?->format('Y-m-d')" inline />
            </div>
            @if (! $employee->exists && auth()->user()->can('access.manage'))
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <h3 class="mb-3 text-sm font-semibold">Toegang</h3>
                    <label class="mb-3 flex items-center gap-2 text-sm"><input type="checkbox" name="invite" value="1" @checked(old('invite')) class="rounded border-slate-300 text-brand-600"> Uitnodiging per e-mail versturen</label>
                    <x-select name="user_group_id" label="Gebruikersgroep" placeholder="— Kies een groep —" :options="$groups" inline />
                </div>
            @endif
        </x-card>

        @if ($canPrivate)
        <x-card title="Persoonlijke gegevens">
            <div class="space-y-4">
                <x-select name="gender" label="Geslacht" placeholder="—" :options="['male' => 'Man', 'female' => 'Vrouw', 'other' => 'Anders']" :selected="$employee->gender" inline />
                <x-field name="date_of_birth" label="Geboortedatum" type="date" :value="$employee->date_of_birth?->format('Y-m-d')" inline />
                <x-field name="nationality" label="Nationaliteit" :value="$employee->nationality" inline />
                <x-field name="private_email" label="Privé e-mail" type="email" :value="$employee->private_email" inline />
                <x-field name="bsn" label="BSN" :value="$employee->bsn" inputmode="numeric" maxlength="11" inline />
                <x-field name="iban" label="IBAN" :value="$employee->iban" inline />
            </div>
        </x-card>
        <x-card title="Adres en noodcontact">
            <div class="space-y-4">
                <x-field name="street" label="Adres" :value="$employee->street" inline />
                <x-field name="zipcode" label="Postcode" :value="$employee->zipcode" inline />
                <x-field name="city" label="Plaatsnaam" :value="$employee->city" inline />
                <x-field name="country" label="Land" :value="$employee->country ?? 'Nederland'" inline />
                <x-field name="emergency_contact_name" label="Noodcontact" :value="$employee->emergency_contact_name" inline />
                <x-field name="emergency_contact_phone" label="Telefoon noodcontact" :value="$employee->emergency_contact_phone" inline />
            </div>
        </x-card>
        @endif
    </div>
</form>
@endsection

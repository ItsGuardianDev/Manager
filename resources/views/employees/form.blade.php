@extends('layouts.app')
@section('title', $employee->exists ? $employee->full_name.' bewerken' : 'Nieuwe medewerker')
@section('content')
@php
    $canPrivate = auth()->user()->can('updatePrivate', $employee);
    $selectedTeams = old('team_ids', $employee->exists ? $employee->teams->pluck('id')->all() : []);
    $selectedDisciplines = old('discipline_ids', $employee->exists ? $employee->disciplines->pluck('id')->all() : []);
@endphp
<form method="POST" enctype="multipart/form-data" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="flex min-h-full flex-col gap-4 p-4 sm:p-5 lg:h-full">
    @csrf
    @if ($employee->exists) @method('PUT') @endif
    @if ($person ?? null)<input type="hidden" name="person_id" value="{{ $person->id }}">@endif
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $employee->exists ? $employee->full_name : 'Medewerkers' }}</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $employee->exists ? 'Medewerker bewerken' : 'Nieuwe medewerker' }}</h1>
        </div>
        <button class="rounded-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">Opslaan</button>
    </div>

    @if (! $employee->exists && $candidates->isNotEmpty())
        <div class="shrink-0 rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-slate-200">
            <label class="mr-3 font-medium text-slate-700" for="person-picker">O.b.v. bestaande persoon</label>
            <select id="person-picker" class="rounded-lg border-0 py-1.5 pl-3 pr-8 text-sm shadow-sm ring-1 ring-inset ring-slate-300" onchange="window.location = this.value ? '{{ route('employees.create') }}?person_id=' + this.value : '{{ route('employees.create') }}'">
                <option value="">— Nieuwe persoon —</option>
                @foreach ($candidates as $candidate)<option value="{{ $candidate->id }}" @selected(($person->id ?? null) === $candidate->id)>{{ $candidate->full_name }}{{ $candidate->email ? ' ('.$candidate->email.')' : '' }}</option>@endforeach
            </select>
            <span class="ml-2 text-xs text-slate-500">De gegevens van de persoon worden voorgevuld en gekoppeld.</span>
        </div>
    @endif

    <div class="grid min-h-0 gap-4 overflow-y-auto lg:flex-1 lg:grid-cols-2 lg:content-start">
        <x-card title="Algemene gegevens" class="lg:overflow-visible">
            <div class="space-y-4">
                <x-field name="first_name" label="Voornaam" :value="$employee->first_name" required inline />
                <x-field name="initials" label="Voorletters" :value="$employee->initials" inline />
                <x-field name="last_name_prefix" label="Tussenvoegsel" :value="$employee->last_name_prefix" inline />
                <x-field name="last_name" label="Achternaam" :value="$employee->last_name" required inline />
                <x-field name="email" label="E-mail werk" type="email" :value="$employee->email" required inline />
                <x-field name="phone" label="Telefoon werk" :value="$employee->phone" inline />
                <x-field name="mobile" label="Mobiel werk" :value="$employee->mobile" inline />
                <x-field name="linkedin_url" label="LinkedIn url" :value="$employee->linkedin_url" inline />
                <x-field name="twitter_url" label="Twitter url" :value="$employee->twitter_url" inline />
                <x-field name="facebook_url" label="Facebook url" :value="$employee->facebook_url" inline />
            </div>
        </x-card>

        <x-card title="Medewerker" class="lg:overflow-visible">
            <div class="space-y-4">
                <x-field name="employee_number" label="Personeelsnummer" :value="$employee->employee_number" hint="Leeg laten voor automatisch nummer." inline />
                <x-field name="job_title" label="Functie" :value="$employee->job_title" inline />
                <x-select name="employee_type" label="Soort medewerker" :options="$employeeTypes" :selected="$employee->employee_type?->value ?? 'own'" inline />
                <div class="grid grid-cols-[8.5rem_1fr] items-start gap-x-4">
                    <span class="pt-1 text-sm font-medium text-slate-700">Lid van team(s)</span>
                    <div class="space-y-1">@forelse ($teams as $id => $name)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="team_ids[]" value="{{ $id }}" @checked(in_array($id, array_map('intval', $selectedTeams), true)) class="rounded border-slate-300 text-brand-600"> {{ $name }}</label>@empty<span class="text-sm text-slate-400">Nog geen teams.</span>@endforelse</div>
                </div>
                <div class="grid grid-cols-[8.5rem_1fr] items-start gap-x-4">
                    <span class="pt-1 text-sm font-medium text-slate-700">Disciplines</span>
                    <div class="space-y-1">@forelse ($disciplines as $id => $name)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="discipline_ids[]" value="{{ $id }}" @checked(in_array($id, array_map('intval', $selectedDisciplines), true)) class="rounded border-slate-300 text-brand-600"> {{ $name }}</label>@empty<span class="text-sm text-slate-400">Nog geen disciplines.</span>@endforelse</div>
                </div>
                <x-select name="manager_id" label="Leidinggevende" placeholder="— Geen —" :options="$managers" :selected="$employee->manager_id" inline />
                <x-field name="hired_at" label="Datum in dienst" type="date" :value="$employee->hired_at?->format('Y-m-d')" inline />
                <div class="grid grid-cols-[8.5rem_1fr] items-start gap-x-4">
                    <label for="photo" class="pt-2 text-sm font-medium text-slate-700">Profielfoto</label>
                    <div>
                        <input id="photo" type="file" name="photo" accept="image/*" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-medium hover:file:bg-slate-200">
                        @error('photo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
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
                <x-select name="marital_status" label="Burgerlijke staat" placeholder="—" :options="$maritalStatuses" :selected="$employee->marital_status" inline />
                <x-field name="nationality" label="Nationaliteit" :value="$employee->nationality" inline />
                <x-field name="private_email" label="E-mail privé" type="email" :value="$employee->private_email" inline />
                <x-field name="private_phone" label="Telefoon privé" :value="$employee->private_phone" inline />
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

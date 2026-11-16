@extends('layouts.app')
@section('title', $person->full_name)
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
@php
    $address = collect([$person->street, trim($person->zipcode.' '.$person->city), $person->country])->filter()->implode(', ');
    $row = fn ($label, $value) => $value ? '<div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">'.e($label).'</dt><dd class="text-right font-medium">'.e($value).'</dd></div>' : '';
@endphp
<div class="flex shrink-0 flex-wrap items-start justify-between gap-4">
    <div class="flex items-center gap-4">
        <span class="grid size-14 place-items-center rounded-full bg-brand-100 text-lg font-semibold text-brand-700">{{ $person->initials_label }}</span>
        <div>
            <a href="{{ route('people.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Personen</a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $person->full_name }}</h1>
                <x-status-badge :active="$person->is_active" />
            </div>
        </div>
    </div>
    <div class="flex gap-2 text-sm">
        <a href="{{ route('people.edit', $person) }}" class="rounded-lg bg-brand-700 px-3.5 py-2 font-semibold text-white hover:bg-brand-800">Bewerken</a>
        <form method="POST" action="{{ route('people.toggle-active', $person) }}" @if ($person->is_active) onsubmit="return confirm('Deze persoon deactiveren?')" @endif>
            @csrf @method('PATCH')
            <button class="rounded-lg px-3.5 py-2 font-medium ring-1 ring-inset {{ $person->is_active ? 'text-rose-700 ring-rose-300 hover:bg-rose-50' : 'text-emerald-700 ring-emerald-300 hover:bg-emerald-50' }}">{{ $person->is_active ? 'Deactiveren' : 'Activeren' }}</button>
        </form>
    </div>
</div>

<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-3 lg:grid-rows-2">
    <x-card title="Gegevens" class="lg:col-start-1 lg:row-span-2 lg:row-start-1">
        <dl class="divide-y divide-slate-100 text-sm">
            {!! $row('E-mail', $person->email) !!}
            {!! $row('Telefoon', $person->phone) !!}
            {!! $row('Mobiel', $person->mobile) !!}
            {!! $row('LinkedIn', $person->linkedin_url) !!}
            {!! $row('Voorletters', $person->initials) !!}
            {!! $row('Geslacht', ucfirst((string) $person->gender)) !!}
            {!! $row('Geboortedatum', $person->date_of_birth?->translatedFormat('d M Y')) !!}
            {!! $row('Adres', $address) !!}
        </dl>
    </x-card>
    <x-card title="Bedrijven ({{ $person->organizations->count() }})" class="lg:col-span-2 lg:col-start-2 lg:row-start-1">
        @if ($person->organizations->isNotEmpty())
            <ul class="divide-y divide-slate-100">
                @foreach ($person->organizations as $organization)
                    <li class="py-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <a href="{{ route('organizations.show', $organization) }}" class="font-medium text-brand-700 hover:underline">{{ $organization->name }}</a>
                                @if ($organization->pivot->is_primary)<span class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-700">Primair</span>@endif
                                <span class="ml-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $organization->type->badgeClasses() }}">{{ $organization->type->label() }}</span>
                            </div>
                            <form method="POST" action="{{ route('links.destroy', [$organization, $person]) }}" onsubmit="return confirm('Dit bedrijf ontkoppelen?')">
                                @csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Ontkoppelen</button>
                            </form>
                        </div>
                        <form method="POST" action="{{ route('links.update', [$organization, $person]) }}" class="mt-2 flex flex-wrap items-center gap-2">
                            @csrf @method('PATCH')
                            <input name="job_title" value="{{ $organization->pivot->job_title }}" placeholder="Functie" class="w-44 rounded-lg border-0 px-2.5 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                            <input name="department" value="{{ $organization->pivot->department }}" placeholder="Afdeling" class="w-36 rounded-lg border-0 px-2.5 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                            <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" name="is_primary" value="1" @checked($organization->pivot->is_primary) class="rounded border-slate-300 text-brand-600"> Primair</label>
                            <button class="rounded-lg px-2.5 py-1.5 text-xs font-medium ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Opslaan</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-slate-500">Nog niet aan een bedrijf gekoppeld.</p>
        @endif

        @if ($availableOrganizations->isNotEmpty())
            <form method="POST" action="{{ route('links.store') }}" class="mt-5 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4">
                @csrf
                <input type="hidden" name="person_id" value="{{ $person->id }}">
                <x-select name="organization_id" label="Koppelen aan bedrijf" placeholder="Kies een bedrijf…" :options="$availableOrganizations->pluck('name', 'id')" class="min-w-52 flex-1" />
                <x-field name="job_title" label="Functie" :keep="false" class="w-44" />
                <button class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-700">Koppelen</button>
            </form>
        @endif
    </x-card>


    <x-notes class="lg:col-span-2 lg:col-start-2 lg:row-start-2" :notes="$person->notes" :action="route('people.notes.store', $person)" />
</div>
</div>
@endsection

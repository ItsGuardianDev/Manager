@extends('layouts.app')
@section('title', $person->full_name)
@section('content')
@php
    $address = collect([$person->street, trim($person->zipcode.' '.$person->city), $person->country])->filter()->implode(', ');
    $row = fn ($label, $value) => $value ? '<div class="flex justify-between gap-4 py-2"><dt class="text-slate-500">'.e($label).'</dt><dd class="break-all text-right font-medium">'.e($value).'</dd></div>' : '';
    $gender = ['male' => 'Man', 'female' => 'Vrouw', 'other' => 'Anders'][$person->gender] ?? null;
@endphp
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="flex shrink-0 flex-wrap items-start justify-between gap-4">
    <div class="flex items-center gap-4">
        <span class="grid size-14 place-items-center rounded-full bg-brand-100 text-lg font-semibold text-brand-700">{{ $person->initials_label }}</span>
        <div>
            <a href="{{ route('people.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Personen</a>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $person->full_name }}</h1>
                @if ($person->employee)<span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-600/20">Medewerker</span>@endif
                @if ($person->organizations->isNotEmpty())<span class="rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-inset ring-sky-600/20">Contactpersoon</span>@endif
                <x-status-badge :active="$person->is_active" />
            </div>
        </div>
    </div>
    <div class="flex gap-2 text-sm">
        @if ($person->employee && auth()->user()->can('view', $person->employee))
            <a href="{{ route('employees.show', $person->employee) }}" class="rounded-lg px-3.5 py-2 font-medium ring-1 ring-inset ring-slate-300 hover:bg-white">Medewerkerprofiel</a>
        @endif
        <a href="{{ route('people.edit', $person) }}" class="rounded-lg bg-brand-700 px-3.5 py-2 font-semibold text-white hover:bg-brand-800">Persoonsgegevens</a>
        <form method="POST" action="{{ route('people.toggle-active', $person) }}" @if ($person->is_active) onsubmit="return confirm('Deze persoon deactiveren?')" @endif>
            @csrf @method('PATCH')
            <button class="rounded-lg px-3.5 py-2 font-medium ring-1 ring-inset {{ $person->is_active ? 'text-rose-700 ring-rose-300 hover:bg-rose-50' : 'text-emerald-700 ring-emerald-300 hover:bg-emerald-50' }}">{{ $person->is_active ? 'Deactiveren' : 'Activeren' }}</button>
        </form>
    </div>
</div>

<div data-tabs data-initial="{{ $tab }}" class="flex min-h-0 flex-col lg:flex-1">
    <div class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 text-sm font-medium" role="tablist">
        @foreach (['overview' => 'Overzicht', 'companies' => 'Contactpersoon bij', 'documents' => 'Documenten'] as $key => $label)
            <button type="button" data-tab-target="{{ $key }}" class="-mb-px whitespace-nowrap border-b-2 border-transparent px-4 py-2.5 text-slate-500 hover:text-slate-800">{{ $label }}</button>
        @endforeach
    </div>
    <div class="min-h-0 flex-1 pt-4">
        <div data-tab-panel="overview" class="grid gap-4 lg:h-full lg:grid-cols-[20rem_1fr_18rem]">
            <div class="space-y-4 lg:overflow-y-auto lg:pr-1">
                <x-card title="Algemeen" class="overflow-visible">
                    <dl class="divide-y divide-slate-100 text-sm">
                        {!! $row('Relatiebeheerder', $person->relationManager?->name) !!}
                        {!! $row('Team', $person->team?->name) !!}
                        {!! $row('Aanspreekvorm', $person->salutation) !!}
                        {!! $row('Geslacht', $gender) !!}
                        {!! $row('Geboortedatum', $person->date_of_birth?->translatedFormat('d-m-Y')) !!}
                        {!! $row('E-mailadres', $person->email) !!}
                        {!! $row('Telefoonnummer', $person->phone) !!}
                        {!! $row('Mobiel', $person->mobile) !!}
                        {!! $row('LinkedIn', $person->linkedin_url) !!}
                        {!! $row('Twitter', $person->twitter_url) !!}
                        {!! $row('Facebook', $person->facebook_url) !!}
                        {!! $row('Aangemaakt op', $person->created_at->format('d-m-Y')) !!}
                    </dl>
                </x-card>
                <x-card title="Adresgegevens" class="overflow-visible"><p class="text-sm">{{ $address ?: '—' }}</p></x-card>
            </div>
            <x-timeline :notes="$person->notes" :action="route('people.notes.store', $person)" class="mx-auto w-full max-w-3xl lg:h-full" />
            <div class="space-y-4 lg:overflow-y-auto">
                <x-card title="Contactpersoon bij ({{ $person->organizations->count() }})" class="overflow-visible">
                    @forelse ($person->organizations as $organization)
                        <div class="mb-2 rounded-lg bg-slate-50 p-3 text-sm">
                            <a href="{{ route('organizations.show', $organization) }}" class="font-medium text-brand-700 hover:underline">{{ $organization->name }}</a>
                            <div class="text-xs text-slate-500">{{ $organization->pivot->job_title }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Geen bedrijven gekoppeld.</p>
                    @endforelse
                </x-card>
            </div>
        </div>

        <div data-tab-panel="companies" class="lg:h-full">
    <x-card title="Bedrijven ({{ $person->organizations->count() }})">
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


        </div>

        <x-documents data-tab-panel="documents" class="lg:h-full" :documents="$person->documents" :action="route('people.documents.store', $person)" />
    </div>
</div>
</div>
@endsection

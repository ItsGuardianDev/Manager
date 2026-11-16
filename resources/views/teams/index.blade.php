@extends('layouts.app')
@section('title', 'Teams')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<x-hr-nav />
<h1 class="shrink-0 text-2xl font-semibold tracking-tight">Teams</h1>
<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-3">
    <x-card title="Teams en teammanagers" flush class="lg:col-span-2">
        <ul class="divide-y divide-slate-100">
        @forelse ($teams as $team)
            <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                <form method="POST" action="{{ route('teams.update', $team) }}" class="flex flex-1 flex-wrap items-center gap-2">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $team->name }}" required class="min-w-40 flex-1 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
                    <select name="manager_id" class="min-w-40 flex-1 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300"><option value="">— Geen manager —</option>@foreach ($managers as $id => $name)<option value="{{ $id }}" @selected($team->manager_id === $id)>{{ $name }}</option>@endforeach</select>
                    <span class="text-xs text-slate-500">{{ $team->employees_count }} medewerkers</span>
                    <button class="text-sm font-medium text-brand-700 hover:underline">Opslaan</button>
                </form>
                <form method="POST" action="{{ route('teams.destroy', $team) }}" onsubmit="return confirm('Dit team verwijderen? Medewerkers blijven bestaan zonder team.')">@csrf @method('DELETE')<button class="text-sm text-rose-600 hover:underline">Verwijderen</button></form>
            </li>
        @empty
            <li class="px-5 py-8 text-center text-sm text-slate-500">Nog geen teams.</li>
        @endforelse
        </ul>
    </x-card>
    <x-card title="Nieuw team">
        <form method="POST" action="{{ route('teams.store') }}" class="space-y-3">
            @csrf
            <x-field name="name" label="Naam" required />
            <x-select name="manager_id" label="Teammanager" placeholder="— Geen —" :options="$managers" />
            <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Team aanmaken</button>
        </form>
    </x-card>
</div>
</div>
@endsection

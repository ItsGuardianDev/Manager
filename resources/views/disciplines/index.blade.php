@extends('layouts.app')
@section('title', 'Disciplines')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<x-hr-nav />
<h1 class="shrink-0 text-2xl font-semibold tracking-tight">Disciplines</h1>
<p class="shrink-0 text-sm text-slate-600">Disciplines worden gebruikt voor de planning: ze beschrijven welk vak een medewerker uitoefent.</p>
<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-3">
    <x-card title="Disciplines" flush class="lg:col-span-2">
        <ul class="divide-y divide-slate-100">
        @forelse ($disciplines as $discipline)
            <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                <form method="POST" action="{{ route('disciplines.update', $discipline) }}" class="flex flex-1 items-center gap-2">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $discipline->name }}" required class="min-w-40 flex-1 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
                    <span class="text-xs text-slate-500">{{ $discipline->employees_count }} medewerkers</span>
                    <button class="text-sm font-medium text-brand-700 hover:underline">Opslaan</button>
                </form>
                <form method="POST" action="{{ route('disciplines.destroy', $discipline) }}" onsubmit="return confirm('Deze discipline verwijderen?')">@csrf @method('DELETE')<button class="text-sm text-rose-600 hover:underline">Verwijderen</button></form>
            </li>
        @empty
            <li class="px-5 py-8 text-center text-sm text-slate-500">Nog geen disciplines.</li>
        @endforelse
        </ul>
    </x-card>
    <x-card title="Nieuwe discipline">
        <form method="POST" action="{{ route('disciplines.store') }}" class="space-y-3">
            @csrf
            <x-field name="name" label="Naam" required />
            <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Aanmaken</button>
        </form>
    </x-card>
</div>
</div>
@endsection

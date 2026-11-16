@extends('layouts.app')
@section('title', 'Klantgroepen')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="shrink-0"><a href="{{ route('organizations.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Bedrijven</a><h1 class="text-2xl font-semibold tracking-tight">Klantgroepen</h1></div>
<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-3">
    <x-card title="Klantgroepen" flush class="lg:col-span-2">
        <ul class="divide-y divide-slate-100">
        @forelse ($groups as $group)
            <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                <form method="POST" action="{{ route('customer-groups.update', $group) }}" class="flex flex-1 items-center gap-2">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $group->name }}" required class="min-w-40 flex-1 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
                    <span class="text-xs text-slate-500">{{ $group->organizations_count }} bedrijven</span>
                    <button class="text-sm font-medium text-brand-700 hover:underline">Opslaan</button>
                </form>
                <form method="POST" action="{{ route('customer-groups.destroy', $group) }}" onsubmit="return confirm('Deze klantgroep verwijderen?')">@csrf @method('DELETE')<button class="text-sm text-rose-600 hover:underline">Verwijderen</button></form>
            </li>
        @empty
            <li class="px-5 py-8 text-center text-sm text-slate-500">Nog geen klantgroepen.</li>
        @endforelse
        </ul>
    </x-card>
    <x-card title="Nieuwe klantgroep">
        <form method="POST" action="{{ route('customer-groups.store') }}" class="space-y-3">
            @csrf
            <x-field name="name" label="Naam" required />
            <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Aanmaken</button>
        </form>
    </x-card>
</div>
</div>
@endsection

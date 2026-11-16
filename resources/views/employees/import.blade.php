@extends('layouts.app')
@section('title', 'Medewerkers importeren')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<x-hr-nav />
<div class="shrink-0">
    <a href="{{ route('employees.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Medewerkers</a>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight">Medewerkers importeren</h1>
</div>
<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-2">
    <x-card title="Upload een CSV-bestand">
        <form method="POST" action="{{ route('employees.import.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <input type="file" name="file" accept=".csv,.txt" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-medium hover:file:bg-slate-200">
                @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <p class="text-sm text-slate-600">Gescheiden door puntkomma of komma, UTF-8. Medewerkers met een bestaand e-mailadres of personeelsnummer worden overgeslagen. Onbekende teams en disciplines worden aangemaakt. De leidinggevende wordt gekoppeld op e-mailadres. Een contract wordt aangemaakt als <em>contract_type</em> is ingevuld.</p>
            <div class="flex items-center gap-3">
                <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Importeren</button>
                <a href="{{ route('employees.import.template') }}" class="text-sm font-medium text-brand-700 hover:underline">Sjabloon downloaden</a>
            </div>
        </form>
        <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500"><span class="font-medium">Kolommen:</span> {{ implode(', ', $fields) }}<br><span class="font-medium">contract_type:</span> {{ implode(', ', $contractTypes) }}<br><span class="font-medium">employment_type:</span> {{ implode(', ', $employmentTypes) }}</p>
    </x-card>
    <x-card title="Resultaat">
        @if (session('import_result'))
            @include('partials.import-result')
        @else
            <p class="text-sm text-slate-500">Na het importeren zie je hier wat is aangemaakt, overgeslagen of fout ging.</p>
        @endif
    </x-card>
</div>
</div>
@endsection

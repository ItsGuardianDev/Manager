@extends('layouts.app')
@section('title', 'Bedrijven importeren')
@section('content')
<div class="mb-6">
    <a href="{{ route('organizations.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Bedrijven</a>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight">Bedrijven importeren</h1>
</div>
@include('partials.import-result')
<x-card title="Upload een Excel- of CSV-bestand" class="max-w-2xl">
    <form method="POST" action="{{ route('organizations.import.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <input type="file" name="file" accept=".csv,.txt,.xlsx" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-medium hover:file:bg-slate-200">
            <p class="text-sm text-slate-600">Een export uit Simplicate (.xlsx) kan direct worden geüpload: Nederlandse kolomnamen en waarden zoals "Klant", "Ja" of "01-03-2024" worden herkend. Alleen het eerste werkblad wordt gelezen.</p>
            @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <p class="text-sm text-slate-600">Gescheiden door puntkomma of komma, UTF-8. Bestaande bedrijven (zelfde naam, KvK- of btw-nummer) worden overgeslagen en nooit overschreven. Onbekende branches en groottes worden aangemaakt; de verantwoordelijke medewerker wordt gekoppeld op e-mailadres.</p>
        <div class="flex items-center gap-3">
            <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Importeren</button>
            <a href="{{ route('organizations.import.template') }}" class="text-sm font-medium text-brand-700 hover:underline">Sjabloon downloaden</a>
        </div>
    </form>
    <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500"><span class="font-medium">Kolommen:</span> {{ implode(', ', $fields) }}</p>
</x-card>
@endsection

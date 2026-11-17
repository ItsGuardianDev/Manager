@extends('layouts.app')
@section('title', 'Nieuwe factuur')
@section('content')
<form method="POST" action="{{ route('invoices.store') }}" class="flex min-h-full flex-col gap-4 p-4 sm:p-5">
    @csrf
    <div class="flex items-center justify-between"><div><a href="{{ route('invoices.index', ['tab' => 'drafts']) }}" class="text-sm text-slate-500 hover:text-slate-800">← Conceptfacturen</a><h1 class="text-2xl font-semibold tracking-tight">Nieuwe factuur</h1></div><button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">Aanmaken</button></div>
    <div class="grid max-w-3xl gap-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2">
        <x-select name="organization_id" label="Debiteur" placeholder="— Kies een bedrijf —" :options="$organizations" :selected="$selected" required />
        <x-select name="project_id" label="Project (optioneel)" placeholder="— Geen project —" :options="$projects->pluck('name', 'id')" />
        <x-field name="subject" label="Onderwerp" class="sm:col-span-2" />
    </div>
</form>
@endsection

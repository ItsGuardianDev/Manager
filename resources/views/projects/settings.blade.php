@extends('layouts.app')
@section('title', 'Projectinstellingen')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="shrink-0"><a href="{{ route('projects.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Projecten</a><h1 class="mt-1 text-2xl font-semibold tracking-tight">Urensoorten, kostensoorten en standaarddiensten</h1></div>
@error('lookup')<div class="shrink-0 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $message }}</div>@enderror
<div class="grid min-h-0 gap-4 overflow-y-auto lg:flex-1 lg:grid-cols-2 lg:content-start">
@foreach ($sections as $section)
    <x-card :title="$section['title']" class="lg:max-h-[26rem]">
        <ul class="divide-y divide-slate-100">
            @foreach ($section['items'] as $item)
                <li class="flex flex-wrap items-center gap-2 py-2">
                    <form method="POST" action="{{ route('project-settings.update', [$section['key'], $item->id]) }}" class="flex flex-1 flex-wrap items-center gap-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $item->name }}" required class="min-w-32 flex-1 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
                        @if (($section['extra'] ?? null) === 'invoice_method')
                            <select name="invoice_method" class="rounded-lg border-0 px-2 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300"><option value="">—</option>@foreach ($methods as $value => $label)<option value="{{ $value }}" @selected($item->invoice_method?->value === $value)>{{ $label }}</option>@endforeach</select>
                        @elseif (isset($section['extra']))
                            <input name="{{ $section['extra'] }}" type="number" step="0.01" min="0" value="{{ $item->{$section['extra']} }}" title="{{ $section['extra_label'] }}" class="w-24 rounded-lg border-0 px-2 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
                        @endif
                        <button class="text-sm font-medium text-brand-700 hover:underline">Opslaan</button>
                    </form>
                    <form method="POST" action="{{ route('project-settings.destroy', [$section['key'], $item->id]) }}" onsubmit="return confirm('Verwijderen?')">@csrf @method('DELETE')<button class="text-sm text-rose-600 hover:underline">Verwijderen</button></form>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('project-settings.store', $section['key']) }}" class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
            @csrf
            <input name="name" required placeholder="Nieuw…" class="min-w-32 flex-1 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
            @if (($section['extra'] ?? null) === 'invoice_method')
                <select name="invoice_method" class="rounded-lg border-0 px-2 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300"><option value="">—</option>@foreach ($methods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            @elseif (isset($section['extra']))
                <input name="{{ $section['extra'] }}" type="number" step="0.01" min="0" placeholder="{{ $section['extra_label'] }}" class="w-40 rounded-lg border-0 px-2 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
            @endif
            <button class="rounded-lg bg-brand-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-800">Toevoegen</button>
        </form>
    </x-card>
@endforeach
</div>
</div>
@endsection

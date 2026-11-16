@extends('layouts.app')
@section('title', 'Personen')
@section('content')
<div class="flex flex-col lg:h-full lg:flex-row">
<div class="flex min-w-0 flex-1 flex-col gap-4 p-4 sm:p-5">
    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 text-sm">
        <button type="button" data-toggle="filters" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 lg:hidden">Filters</button>
        <details class="relative">
            <summary class="cursor-pointer list-none rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Opties</summary>
            <div class="absolute right-0 z-20 mt-2 w-52 rounded-lg bg-white p-1 shadow-lg ring-1 ring-slate-200">
                <a href="{{ route('people.import') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Personen importeren</a>
                <a href="{{ route('people.export', [...request()->query(), 'format' => 'csv']) }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Exporteren als CSV</a>
                <a href="{{ route('people.import.template') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Importsjabloon downloaden</a>
            </div>
        </details>
        <a href="{{ route('people.export', request()->query()) }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Exporteren naar Excel</a>
        <a href="{{ route('people.create') }}" class="rounded-full bg-brand-700 px-4 py-2 font-semibold text-white hover:bg-brand-800">+ Nieuw</a>
    </div>

    <div class="flex min-h-[28rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-brand-50 text-left text-xs font-semibold text-slate-700">
                <tr>
                    <th class="whitespace-nowrap px-4 py-3"><x-sort-link column="last_name" :sort="$sort" :direction="$direction">Naam ({{ $people->total() }})</x-sort-link></th>
                    <th class="whitespace-nowrap px-4 py-3">Bedrijven & functie</th>
                    <th class="whitespace-nowrap px-4 py-3">Contactgegevens</th>
                    <th class="whitespace-nowrap px-4 py-3"><x-sort-link column="created_at" :sort="$sort" :direction="$direction">Aangemaakt op</x-sort-link></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($people as $person)
                    <tr class="align-top hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('people.show', $person) }}" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">{{ $person->full_name }}</a>
                            @if ($person->employee)<span class="ml-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand-700">Medewerker</span>@endif
                            @unless ($person->is_active)<div class="text-xs text-slate-500">(inactief)</div>@endunless
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            @forelse ($person->organizations as $organization)
                                <div>@if ($organization->pivot->job_title){{ $organization->pivot->job_title }} @endif<a href="{{ route('organizations.show', $organization) }}" class="text-brand-700 hover:underline">@ {{ $organization->name }}</a></div>
                            @empty <span class="text-slate-400">—</span> @endforelse
                        </td>
                        <td class="px-4 py-3 text-brand-700">
                            @foreach (array_filter([$person->email, $person->phone, $person->mobile]) as $contact)<div>{{ $contact }}</div>@endforeach
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $person->created_at->format('d-m-Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-12 text-center text-slate-500">Geen personen gevonden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pager :paginator="$people" />
    </div>
</div>

<aside id="filters" class="hidden w-full shrink-0 overflow-y-auto bg-white px-5 py-5 ring-1 ring-slate-200 lg:block lg:w-72 lg:rounded-none">
    <form method="GET" data-autosubmit>
        <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $direction }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoeken…"
               class="mb-6 block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600">
        <fieldset class="mb-6">
            <legend class="mb-2 text-sm font-semibold text-slate-900">Datum aangemaakt</legend>
            <div class="flex flex-col gap-2">
                <input type="date" name="created_from" value="{{ request('created_from') }}" class="min-w-0 flex-1 rounded-lg border-0 bg-white px-2 py-1.5 text-xs shadow-sm ring-1 ring-inset ring-slate-300">
                <input type="date" name="created_to" value="{{ request('created_to') }}" class="min-w-0 flex-1 rounded-lg border-0 bg-white px-2 py-1.5 text-xs shadow-sm ring-1 ring-inset ring-slate-300">
            </div>
        </fieldset>
        <x-filter-group title="Soort persoon" name="kind" type="radio" default="all" :options="['all' => 'Alle', 'contact' => 'Contactpersoon', 'employee' => 'Medewerker']" />
        <x-filter-group title="Status" name="status" type="radio" :options="['active' => 'Actief', 'inactive' => 'Inactief', 'all' => 'Alle']" />
        <div class="max-h-96 overflow-y-auto">
            <x-filter-group title="Bedrijf" name="organization_id" :options="$organizations->pluck('name', 'id')" />
        </div>
        <a href="{{ route('people.index') }}" class="text-sm text-brand-700 hover:underline">Filters wissen</a>
    </form>
</aside>
</div>
@endsection

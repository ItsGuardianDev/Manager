@extends('layouts.app')
@section('title', 'Medewerkers')
@section('content')
<div class="flex flex-col lg:h-full lg:flex-row">
<div class="flex min-w-0 flex-1 flex-col gap-4 p-4 sm:p-5">
    <x-hr-nav />
    <div class="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($stats as $label => $value)
            <div class="rounded-2xl bg-white px-5 py-3 shadow-sm ring-1 ring-slate-200">
                <div class="text-2xl font-semibold text-ink">{{ $value }}</div>
                <div class="text-xs text-slate-500">{{ $label }}</div>
            </div>
        @endforeach
    </div>
    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 text-sm">
        <button type="button" data-toggle="filters" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 lg:hidden">Filters</button>
        @can('create', \App\Models\Employee::class)
        <details class="relative">
            <summary class="cursor-pointer list-none rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Opties</summary>
            <div class="absolute right-0 z-20 mt-2 w-56 rounded-lg bg-white p-1 shadow-lg ring-1 ring-slate-200">
                <a href="{{ route('employees.import') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Medewerkers importeren</a>
                <a href="{{ route('employees.import.template') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">Importsjabloon downloaden</a>
            </div>
        </details>
        @endcan
        <a href="{{ route('employees.export', request()->query()) }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Exporteren naar Excel</a>
        @can('create', \App\Models\Employee::class)
            <a href="{{ route('employees.create') }}" class="rounded-full bg-brand-700 px-4 py-2 font-semibold text-white hover:bg-brand-800">+ Nieuwe medewerker</a>
        @endcan
    </div>

    <div class="flex min-h-[28rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-brand-50 text-left text-xs font-semibold text-slate-700">
                <tr>
                    <th class="whitespace-nowrap px-4 py-3"><x-sort-link column="last_name" :sort="$sort" :direction="$direction">Naam ({{ $employees->total() }})</x-sort-link></th>
                    <th class="whitespace-nowrap px-4 py-3"><x-sort-link column="job_title" :sort="$sort" :direction="$direction">Functie</x-sort-link></th>
                    <th class="whitespace-nowrap px-4 py-3">Team</th>
                    <th class="whitespace-nowrap px-4 py-3">Leidinggevende</th>
                    <th class="whitespace-nowrap px-4 py-3">Contract</th>
                    <th class="whitespace-nowrap px-4 py-3"><x-sort-link column="hired_at" :sort="$sort" :direction="$direction">In dienst</x-sort-link></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $employee)
                    <tr class="align-top hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('employees.show', $employee) }}" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">{{ $employee->full_name }}</a>
                            <div class="text-xs text-slate-500">{{ $employee->email }}</div>
                            @unless ($employee->is_employed)<div class="text-xs text-rose-600">Uit dienst{{ $employee->left_at ? ' per '.$employee->left_at->format('d-m-Y') : '' }}</div>@endunless
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $employee->job_title ?? '—' }}<div class="text-xs text-slate-500">{{ $employee->discipline?->name }}</div></td>
                        <td class="px-4 py-3 text-slate-700">{{ $employee->team?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $employee->manager?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            @if ($employee->currentContract && auth()->user()->can('viewContracts', $employee))
                                {{ $employee->currentContract->contract_type->label() }}
                                <div class="text-xs text-slate-500">{{ $employee->currentContract->employment_type->label() }}@if ($employee->currentContract->hours_per_week) · {{ rtrim(rtrim($employee->currentContract->hours_per_week, '0'), '.') }} u/week @endif</div>
                            @else — @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $employee->hired_at?->format('d-m-Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">Geen medewerkers gevonden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pager :paginator="$employees" />
    </div>
</div>

<aside id="filters" class="hidden w-full shrink-0 overflow-y-auto bg-white px-5 py-5 ring-1 ring-slate-200 lg:block lg:w-72">
    <form method="GET" data-autosubmit>
        <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $direction }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoeken op naam, e-mail, functie…"
               class="mb-6 block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600">
        <x-filter-group title="Status" name="status" type="radio" :options="['active' => 'In dienst', 'upcoming' => 'Nog niet gestart', 'left' => 'Uit dienst', 'all' => 'Alle']" />
        <x-filter-group title="Team" name="team_id" :options="$teams" />
        <x-filter-group title="Discipline" name="discipline_id" :options="$disciplines" />
        <x-filter-group title="Contractsoort" name="contract_type" :options="\App\Enums\ContractType::options()" />
        @if ($managers->isNotEmpty())<x-filter-group title="Leidinggevende" name="manager_id" :options="$managers" />@endif
        <a href="{{ route('employees.index') }}" class="text-sm text-brand-700 hover:underline">Filters wissen</a>
    </form>
</aside>
</div>
@endsection

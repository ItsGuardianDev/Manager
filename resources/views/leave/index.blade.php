@extends('layouts.app')
@section('title', 'Verlofaanvragen')
@section('content')
@php($input = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<x-hr-nav />
<div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold tracking-tight">Verlofaanvragen</h1>
    <div class="flex gap-1 text-sm">
        @foreach (['pending' => 'In afwachting', 'approved' => 'Goedgekeurd', 'rejected' => 'Afgewezen', 'all' => 'Alle'] as $key => $label)
            <a href="{{ route('leave-requests.index', ['status' => $key]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $status === $key ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
<div class="flex min-h-[24rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:min-h-0 lg:flex-1">
    <div class="min-h-0 flex-1 overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-brand-50 text-left text-xs font-semibold text-slate-700">
                <tr><th class="px-4 py-3">Medewerker</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Soort</th><th class="px-4 py-3">Uren</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Afhandelen</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($requests as $leaveRequest)
                <tr class="align-top">
                    <td class="px-4 py-3"><a href="{{ route('employees.show', [$leaveRequest->employee, 'tab' => 'leave']) }}" class="font-medium hover:text-brand-700 hover:underline">{{ $leaveRequest->employee->full_name }}</a><div class="text-xs text-slate-500">{{ $leaveRequest->employee->team?->name }}</div></td>
                    <td class="whitespace-nowrap px-4 py-3">{{ $leaveRequest->start_date->format('d-m-Y') }} – {{ $leaveRequest->end_date->format('d-m-Y') }}@if ($leaveRequest->note)<div class="text-xs text-slate-500">{{ $leaveRequest->note }}</div>@endif</td>
                    <td class="px-4 py-3">{{ $leaveRequest->leaveType->name }}</td>
                    <td class="px-4 py-3">{{ rtrim(rtrim(number_format($leaveRequest->hours, 2, ',', ''), '0'), ',') }}</td>
                    <td class="px-4 py-3">{{ $leaveRequest->status->label() }}@if ($leaveRequest->decider)<div class="text-xs text-slate-500">{{ $leaveRequest->decider->name }}, {{ $leaveRequest->decided_at?->format('d-m-Y') }}</div>@endif</td>
                    <td class="px-4 py-3">
                        @if ($leaveRequest->status === \App\Enums\LeaveStatus::Pending)
                            @can('approveLeave', $leaveRequest->employee)
                            <form method="POST" action="{{ route('leave-requests.decide', $leaveRequest) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <input name="decision_note" placeholder="Toelichting (optioneel)" class="{{ $input }} !w-44 !py-1.5">
                                <button name="decision" value="approve" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Goedkeuren</button>
                                <button name="decision" value="reject" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-300 hover:bg-rose-50">Afwijzen</button>
                            </form>
                            @else <span class="text-xs text-slate-400">Geen rechten</span> @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">Geen aanvragen gevonden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-pager :paginator="$requests" />
</div>
</div>
@endsection

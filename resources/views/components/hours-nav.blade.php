@props(['active' => 'overview'])
@php
    $user = auth()->user();
    $reviews = $user->can('hours.approve') || $user->can('hours.manage') || ($user->employee?->managedProjects()->exists() ?? false);
    $items = ['overview' => ['Overzicht', route('hours.index')], 'calendar' => ['Agenda', route('hours.calendar')], 'week' => ['Weekstaat', route('hours.week')]]
        + ($reviews ? ['approvals' => ['Goedkeuren', route('hours.approvals')]] : [])
        + ['report' => ['Rapportage', route('hours.report')]];
@endphp
<nav class="flex shrink-0 gap-1 overflow-x-auto text-sm" aria-label="Uren">
    @foreach ($items as $key => [$label, $url])
        <a href="{{ $url }}" class="whitespace-nowrap rounded-full px-4 py-1.5 font-medium {{ $active === $key ? 'bg-ink text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
    @endforeach
</nav>

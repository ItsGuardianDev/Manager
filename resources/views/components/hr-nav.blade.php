@php
    $user = auth()->user();
    $isManager = $user->employee && \App\Models\Employee::where('manager_id', $user->employee->id)->exists();
    $canApprove = $isManager || $user->can('leave.approve') || $user->can('leave.manage');
    $pending = $canApprove
        ? \App\Models\LeaveRequest::where('status', 'pending')
            ->when(! ($user->can('leave.approve') || $user->can('leave.manage')), fn ($query) => $query->whereHas('employee', fn ($query) => $query->where('manager_id', $user->employee->id)))
            ->count()
        : 0;
    $items = array_filter([
        $user->can('viewAny', \App\Models\Employee::class) ? ['employees.index', 'employees.*', 'Medewerkers', 0] : null,
        $canApprove ? ['leave-requests.index', 'leave-requests.*', 'Verlofaanvragen', $pending] : null,
        $user->can('absence.manage') ? ['absences.index', 'absences.*', 'Verzuim', 0] : null,
        $user->can('leave.manage') ? ['collective-leave.index', 'collective-leave.*', 'Collectief verlof', 0] : null,
        $user->can('reports.view') ? ['reports.leave', 'reports.*', 'Rapportages', 0] : null,
        $user->can('create', \App\Models\Employee::class) ? ['teams.index', 'teams.*', 'Teams', 0] : null,
        $user->can('create', \App\Models\Employee::class) ? ['disciplines.index', 'disciplines.*', 'Disciplines', 0] : null,
        $user->can('access.manage') ? ['user-groups.index', 'user-groups.*', 'Rechten', 0] : null,
    ]);
@endphp
@if (count($items) > 0)
<nav class="flex shrink-0 gap-1 overflow-x-auto text-sm" aria-label="Medewerkers">
    @foreach ($items as [$route, $match, $label, $badge])
        <a href="{{ route($route) }}" class="flex items-center gap-2 whitespace-nowrap rounded-full px-4 py-1.5 font-medium {{ request()->routeIs($match) && ! (request()->routeIs('employees.show') && $route !== 'employees.index') ? 'bg-ink text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">
            {{ $label }}
            @if ($badge > 0)<span class="grid min-w-5 place-items-center rounded-full bg-rose-500 px-1.5 text-xs font-semibold text-white">{{ $badge }}</span>@endif
        </a>
    @endforeach
</nav>
@endif

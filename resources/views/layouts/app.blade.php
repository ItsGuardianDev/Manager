<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CRM') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col text-ink antialiased lg:h-full lg:flex-row lg:overflow-hidden">
@php
    $user = auth()->user();
    $hrUrl = $user->can('viewAny', \App\Models\Employee::class) ? route('employees.index') : ($user->employee ? route('employees.show', $user->employee) : null);
    $unreadNotifications = $user->unreadNotifications()->count();
    $icons = [
        'building' => '<path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16M14 9h5a1 1 0 0 1 1 1v11M3 21h18M8 8h2M8 12h2M8 16h2"/>',
        'people' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 4.7a3.5 3.5 0 0 1 0 6.6M18 14.5c2 .7 3.5 2.5 3.5 5.5"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M16 15h2"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8L3 12Z"/><circle cx="7.5" cy="8.5" r="1.2"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'badge' => '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="10" r="2.5"/><path d="M8 17c.6-2 2.2-3 4-3s3.4 1 4 3"/>',
    ];
    $sections = [
        'Relaties' => [['organizations.index', 'organizations.*', 'Bedrijven', 'building', true], ['people.index', 'people.*', 'Personen', 'people', true], ['debtors.index', 'debtors.*', 'Debiteuren', 'wallet', true]],
        'Projecten' => [['projects.index', 'projects.*,purchases.*,project-settings.*', 'Projecten', 'folder', $user->can('projects.view')], ['services.index', 'services.*', 'Diensten', 'tag', $user->can('projects.view')], ['hours.index', 'hours.*', 'Uren', 'clock', $user->can('hours.register') || $user->can('hours.approve') || $user->can('hours.manage')], ['invoices.index', 'invoices.*', 'Facturen', 'wallet', $user->can('invoices.view')]],
        'Organisatie' => [[null, 'employees.*,leave-requests.*,absences.*,collective-leave.*,reports.*,teams.*,disciplines.*,user-groups.*', 'Medewerkers', 'badge', (bool) $hrUrl]],
    ];
@endphp
<div class="flex h-14 shrink-0 items-center justify-between bg-ink px-4 text-white lg:hidden">
    <a href="{{ route('organizations.index') }}" class="flex items-center gap-3"><span class="grid size-8 place-items-center rounded-full bg-brand-500 text-sm font-extrabold">m</span><span class="font-semibold tracking-tight">CRM</span></a>
    <button type="button" data-toggle="sidebar" class="grid size-9 place-items-center rounded-lg hover:bg-white/10" aria-label="Menu"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</div>
<aside id="sidebar" class="hidden w-full shrink-0 flex-col bg-ink text-white lg:flex lg:w-56">
    <a href="{{ route('organizations.index') }}" class="hidden h-14 items-center gap-3 px-5 lg:flex"><span class="grid size-8 place-items-center rounded-full bg-brand-500 text-sm font-extrabold">m</span><span class="font-semibold tracking-tight">CRM</span></a>
    <nav class="min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-3 text-sm font-medium">
        @foreach ($sections as $title => $items)
            @if (collect($items)->contains(fn ($item) => $item[4]))
                <div>
                    <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $title }}</p>
                    @foreach ($items as [$route, $match, $label, $icon, $visible])
                        @if ($visible)
                            <a href="{{ $route ? route($route) : $hrUrl }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs(...explode(',', $match)) ? 'bg-brand-500 text-white' : 'text-slate-300 hover:bg-white/10' }}">
                                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>{{ $label }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>
    <div class="shrink-0 space-y-1 border-t border-white/10 p-3 text-sm">
        <a href="{{ route('notifications.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('notifications.*') ? 'bg-brand-500' : 'text-slate-300 hover:bg-white/10' }}">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8Z"/><path d="M10 21h4"/></svg>Meldingen
            @if ($unreadNotifications > 0)<span class="ml-auto grid min-w-5 place-items-center rounded-full bg-rose-500 px-1.5 text-[11px] font-semibold leading-5">{{ $unreadNotifications }}</span>@endif
        </a>
        <details class="group relative">
            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/10">
                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-white/15 text-xs font-semibold">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <span class="min-w-0 truncate">{{ $user->name }}</span>
            </summary>
            <div class="absolute bottom-full left-0 z-30 mb-1 w-full rounded-xl bg-white p-1 text-ink shadow-lg ring-1 ring-slate-200">
                <p class="truncate px-3 py-2 text-xs text-slate-500">{{ $user->email }}</p>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left hover:bg-slate-100">Uitloggen</button></form>
            </div>
        </details>
    </div>
</aside>

<main class="min-h-0 min-w-0 flex-1 lg:overflow-hidden">@yield('content')</main>

@if (session('status'))
    <div id="toast" class="fixed bottom-5 right-5 z-50 rounded-xl bg-ink px-4 py-3 text-sm text-white shadow-lg" role="status">{{ session('status') }}</div>
@endif
</body>
</html>

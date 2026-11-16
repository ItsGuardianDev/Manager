<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CRM') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col text-ink antialiased lg:h-full lg:overflow-hidden">
<header class="flex h-14 shrink-0 items-center gap-6 bg-ink px-4 text-white sm:px-6">
    <a href="{{ route('organizations.index') }}" class="flex items-center gap-3">
        <span class="grid size-8 place-items-center rounded-full bg-brand-500 text-sm font-extrabold">m</span>
        <span class="font-semibold tracking-tight">CRM</span>
    </a>
    <nav class="flex items-center gap-1 text-sm font-medium">
        @php
            $tabs = [['organizations.index', 'organizations.*', 'Bedrijven'], ['people.index', 'people.*', 'Personen'], ['debtors.index', 'debtors.*', 'Debiteuren']];
            $hrUrl = auth()->user()->can('viewAny', \App\Models\Employee::class)
                ? route('employees.index')
                : (auth()->user()->employee ? route('employees.show', auth()->user()->employee) : null);
        @endphp
        @foreach ($tabs as [$route, $match, $label])
            <a href="{{ route($route) }}" class="rounded-full px-4 py-1.5 {{ request()->routeIs($match) ? 'bg-brand-500 text-white' : 'text-slate-300 hover:bg-white/10' }}">{{ $label }}</a>
        @endforeach
        @can('projects.view')
            <a href="{{ route('projects.index') }}" class="rounded-full px-4 py-1.5 {{ request()->routeIs('projects.*', 'purchases.*', 'project-settings.*') ? 'bg-brand-500 text-white' : 'text-slate-300 hover:bg-white/10' }}">Projecten</a>
        @endcan
        @if ($hrUrl)
            <a href="{{ $hrUrl }}" class="rounded-full px-4 py-1.5 {{ request()->routeIs('employees.*', 'leave-requests.*', 'absences.*', 'collective-leave.*', 'reports.*', 'teams.*', 'disciplines.*', 'user-groups.*') ? 'bg-brand-500 text-white' : 'text-slate-300 hover:bg-white/10' }}">Medewerkers</a>
        @endif
    </nav>
    @php($unreadNotifications = auth()->user()->unreadNotifications()->count())
    <a href="{{ route('notifications.index') }}" class="relative ml-auto grid size-9 place-items-center rounded-full hover:bg-white/10" title="Meldingen" aria-label="Meldingen">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8Z"/><path d="M10 21h4"/></svg>
        @if ($unreadNotifications > 0)<span class="absolute right-0.5 top-0.5 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold leading-4">{{ $unreadNotifications }}</span>@endif
    </a>
    <details class="relative">
        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-white/10">
            <span class="grid size-8 place-items-center rounded-full bg-white/15 text-xs font-semibold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="hidden text-sm sm:inline">{{ auth()->user()->name }}</span>
        </summary>
        <div class="absolute right-0 z-30 mt-2 w-56 rounded-xl bg-white p-1 text-sm text-ink shadow-lg ring-1 ring-slate-200">
            <p class="truncate px-3 py-2 text-xs text-slate-500">{{ auth()->user()->email }}</p>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left hover:bg-slate-100">Uitloggen</button></form>
        </div>
    </details>
</header>

<main class="min-h-0 flex-1 lg:overflow-hidden">@yield('content')</main>

@if (session('status'))
    <div id="toast" class="fixed bottom-5 right-5 z-50 rounded-xl bg-ink px-4 py-3 text-sm text-white shadow-lg" role="status">{{ session('status') }}</div>
@endif
</body>
</html>

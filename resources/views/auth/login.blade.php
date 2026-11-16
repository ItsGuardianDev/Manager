<!DOCTYPE html>
<html lang="nl" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-full place-items-center px-4 text-ink antialiased">
<div class="w-full max-w-sm">
    <div class="mb-8 text-center">
        <span class="mx-auto grid size-16 place-items-center rounded-full bg-brand-500 text-3xl font-extrabold text-white">m</span>
        <h1 class="mt-5 text-2xl font-semibold tracking-tight">Inloggen bij {{ config('app.name') }}</h1>
    </div>
    <form method="POST" action="{{ route('login') }}" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @csrf
        <x-field name="email" label="E-mailadres" type="email" autocomplete="username" required autofocus />
        <x-field name="password" label="Wachtwoord" type="password" autocomplete="current-password" required :keep="false" />
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600"> Onthoud mij
        </label>
        <button class="w-full rounded-full bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Inloggen</button>
    </form>
</div>
</body>
</html>

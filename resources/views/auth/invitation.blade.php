<!DOCTYPE html>
<html lang="nl" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account activeren · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-full place-items-center p-4 text-ink antialiased">
<form method="POST" action="{{ route('invitation.store', $token) }}" class="w-full max-w-sm space-y-4 rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
    @csrf
    <h1 class="text-xl font-semibold tracking-tight">Account activeren</h1>
    <p class="text-sm text-slate-600">Kies een wachtwoord van minimaal 10 tekens om in te loggen.</p>
    <input type="hidden" name="email" value="{{ $email }}">
    <p class="text-sm font-medium">{{ $email }}</p>
    <x-field name="password" label="Wachtwoord" type="password" required autocomplete="new-password" :keep="false" />
    <x-field name="password_confirmation" label="Herhaal wachtwoord" type="password" required autocomplete="new-password" :keep="false" />
    <button class="w-full rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Activeren en inloggen</button>
</form>
</body>
</html>

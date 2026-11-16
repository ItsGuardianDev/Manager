@extends('layouts.app')
@section('title', 'Rechten')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<x-hr-nav />
<h1 class="shrink-0 text-2xl font-semibold tracking-tight">Gebruikersgroepen en rechten</h1>
@error('group')<div class="shrink-0 rounded-xl bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ $message }}</div>@enderror
<div class="grid min-h-0 gap-4 lg:flex-1 lg:grid-cols-3">
    <div class="space-y-4 overflow-y-auto lg:col-span-2">
        @foreach ($groups as $group)
            <x-card :title="$group->name.' ('.$group->users_count.' gebruikers)'" class="overflow-visible">
                <form method="POST" action="{{ route('user-groups.update', $group) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-2"><x-field name="name" label="Naam" :value="$group->name" :keep="false" required /><x-field name="description" label="Omschrijving" :value="$group->description" :keep="false" /></div>
                    <div class="grid gap-1 sm:grid-cols-2">
                        @foreach ($permissions as $permission)
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="permissions[]" value="{{ $permission->value }}" @checked($group->grants($permission)) class="mt-0.5 rounded border-slate-300 text-brand-600"> {{ $permission->label() }}</label>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-4"><button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Opslaan</button></div>
                </form>
                @if ($group->users_count === 0)
                    <form method="POST" action="{{ route('user-groups.destroy', $group) }}" onsubmit="return confirm('Deze groep verwijderen?')" class="mt-2">@csrf @method('DELETE')<button class="text-sm text-rose-600 hover:underline">Groep verwijderen</button></form>
                @endif
            </x-card>
        @endforeach
    </div>
    <div class="flex min-h-0 flex-col gap-4">
        <x-card title="Gebruikers">
            <ul class="space-y-2">
                @foreach ($users as $user)
                    <li><form method="POST" action="{{ route('users.group', $user) }}" class="flex items-center gap-2">
                        @csrf @method('PATCH')
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $user->name }}<span class="block truncate text-xs text-slate-500">{{ $user->email }}</span></span>
                        <select name="user_group_id" data-submit-on-change class="w-32 rounded-lg border-0 px-2 py-1.5 text-xs shadow-sm ring-1 ring-inset ring-slate-300" onchange="this.form.submit()">
                            <option value="">Geen</option>@foreach ($groups as $group)<option value="{{ $group->id }}" @selected($user->user_group_id === $group->id)>{{ $group->name }}</option>@endforeach
                        </select></form></li>
                @endforeach
            </ul>
        </x-card>
        <x-card title="Nieuwe groep">
            <form method="POST" action="{{ route('user-groups.store') }}" class="space-y-3">
                @csrf
                <x-field name="name" label="Naam" required />
                <x-field name="description" label="Omschrijving" />
                <p class="text-xs text-slate-500">Vink na het aanmaken de rechten aan en sla op.</p>
                <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Groep aanmaken</button>
            </form>
        </x-card>
    </div>
</div>
</div>
@endsection

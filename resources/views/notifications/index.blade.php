@extends('layouts.app')
@section('title', 'Meldingen')
@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-5 lg:h-full">
<div class="flex shrink-0 items-center justify-between"><h1 class="text-2xl font-semibold tracking-tight">Meldingen</h1>
    @if ($notifications->whereNull('read_at')->isNotEmpty())<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="rounded-full bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Alles als gelezen markeren</button></form>@endif</div>
<x-card flush class="lg:flex-1">
    <ul class="divide-y divide-slate-100">
        @forelse ($notifications as $notification)
            <li class="flex items-start gap-3 px-5 py-3 {{ $notification->read_at ? '' : 'bg-amber-50/60' }}">
                <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-slate-300' : 'bg-rose-500' }}"></span>
                <div class="min-w-0 flex-1 text-sm"><p class="font-medium">Budget overschreden in {{ $notification->data['project'] ?? 'project' }}</p><p class="text-slate-600">{{ $notification->data['message'] ?? '' }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $notification->created_at->translatedFormat('j M Y H:i') }}</p></div>
                @isset($notification->data['project_id'])<a href="{{ route('projects.show', $notification->data['project_id']) }}" class="shrink-0 text-sm font-medium text-brand-700 hover:underline">Bekijk project</a>@endisset
            </li>
        @empty
            <li class="px-5 py-10 text-center text-sm text-slate-500">Geen meldingen.</li>
        @endforelse
    </ul>
</x-card>
</div>
@endsection

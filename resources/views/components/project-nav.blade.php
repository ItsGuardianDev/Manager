@props(['active' => 'active'])
@php($items = ['active' => ['Actief', route('projects.index', ['tab' => 'active'])], 'closed' => ['Gesloten', route('projects.index', ['tab' => 'closed'])], 'all' => ['Alle', route('projects.index', ['tab' => 'all'])], 'purchasing' => ['Projectinkoop', route('purchases.index')]])
<nav class="flex shrink-0 gap-1 overflow-x-auto text-sm" aria-label="Projecten">
    @foreach ($items as $key => [$label, $url])
        <a href="{{ $url }}" class="whitespace-nowrap rounded-full px-4 py-1.5 font-medium {{ $active === $key ? 'bg-ink text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
    @endforeach
</nav>

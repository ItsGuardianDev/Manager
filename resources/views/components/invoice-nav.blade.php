@props(['active' => 'ready'])
@php($items = ['ready' => 'Te factureren', 'drafts' => 'Conceptfacturen', 'outstanding' => 'Openstaande facturen', 'all' => 'Alle facturen'])
<nav class="flex shrink-0 gap-1 overflow-x-auto text-sm" aria-label="Facturen">
    @foreach ($items as $key => $label)
        <a href="{{ route('invoices.index', ['tab' => $key]) }}" class="whitespace-nowrap rounded-full px-4 py-1.5 font-medium {{ $active === $key ? 'bg-ink text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
    @endforeach
    <a href="{{ route('invoices.report') }}" class="whitespace-nowrap rounded-full px-4 py-1.5 font-medium {{ $active === 'report' ? 'bg-ink text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50' }}">Rapportages</a>
</nav>

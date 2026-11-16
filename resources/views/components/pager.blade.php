@props(['paginator'])
<div class="flex shrink-0 items-center justify-between border-t border-slate-100 px-5 py-3 text-sm text-slate-600">
    <span>{{ $paginator->total() === 0 ? '0' : $paginator->firstItem().'–'.$paginator->lastItem() }} van {{ $paginator->total() }}</span>
    <div class="flex items-center gap-2">
        <span class="text-xs text-slate-500">Pagina {{ $paginator->currentPage() }} van {{ $paginator->lastPage() }}</span>
        @if ($paginator->onFirstPage())
            <span class="rounded-full px-3 py-1.5 text-slate-300 ring-1 ring-inset ring-slate-200">← Vorige</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="rounded-full px-3 py-1.5 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">← Vorige</a>
        @endif
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="rounded-full bg-brand-700 px-3 py-1.5 font-medium text-white hover:bg-brand-800">Volgende →</a>
        @else
            <span class="rounded-full px-3 py-1.5 text-slate-300 ring-1 ring-inset ring-slate-200">Volgende →</span>
        @endif
    </div>
</div>

@props(['column', 'sort', 'direction'])
@php($next = $sort === $column && $direction === 'asc' ? 'desc' : 'asc')
<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $next, 'page' => null]) }}" class="inline-flex items-center gap-1 hover:text-slate-900">
    {{ $slot }}@if ($sort === $column)<span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif
</a>

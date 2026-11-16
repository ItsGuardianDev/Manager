@props(['title' => null, 'flush' => false])
<section {{ $attributes->merge(['class' => 'flex min-h-0 flex-col rounded-2xl bg-white shadow-sm ring-1 ring-slate-200']) }}>
    @if ($title)
        <header class="flex shrink-0 items-center justify-between px-5 pb-1 pt-4">
            <h2 class="text-sm font-semibold text-ink">{{ $title }}</h2>
            {{ $actions ?? '' }}
        </header>
    @endif
    <div class="min-h-0 flex-1 overflow-y-auto {{ $flush ? '' : 'p-5' }}">{{ $slot }}</div>
    {{ $footer ?? '' }}
</section>

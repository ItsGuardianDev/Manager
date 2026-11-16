@props(['notes', 'action', 'types' => null, 'canPost' => true, 'canDelete' => true, 'title' => null])
<section {{ $attributes->merge(['class' => 'flex min-h-0 flex-col']) }} data-timeline>
    @if ($canPost)
    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="shrink-0 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        @csrf
        <textarea name="body" rows="2" required placeholder="Laat een bericht achter op de tijdlijn" class="block w-full resize-none border-0 p-0 text-sm placeholder:text-slate-400 focus:ring-0">{{ old('body') }}</textarea>
        @error('body')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        @error('attachment')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                @if ($types)
                    <select name="type" class="rounded-lg border-0 py-1.5 pl-3 pr-8 text-xs shadow-sm ring-1 ring-inset ring-slate-300">@foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                @endif
                <label class="flex cursor-pointer items-center gap-1 rounded-full px-3 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                    <span aria-hidden="true">📎</span> <span data-file-label>Voeg bijlage toe</span>
                    <input type="file" name="attachment" class="sr-only" data-file-input>
                </label>
            </div>
            <button class="rounded-full bg-brand-700 px-4 py-1.5 text-sm font-semibold text-white hover:bg-brand-800">Toevoegen</button>
        </div>
    </form>
    @endif

    <div class="mt-4 flex shrink-0 flex-wrap gap-2">
        <input type="search" data-timeline-q placeholder="Zoek in tijdlijn" class="min-w-0 flex-1 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600">
        <select data-timeline-type class="rounded-lg border-0 py-1.5 pl-3 pr-8 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
            <option value="">Alle categorieën</option>
            @foreach (\App\Enums\NoteType::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
    </div>

    <ol class="mt-4 min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
        @forelse ($notes as $note)
            @php($type = \App\Enums\NoteType::tryFrom($note->type) ?? \App\Enums\NoteType::Note)
            <li data-timeline-item data-type="{{ $type->value }}" data-text="{{ mb_strtolower($note->body.' '.$note->attachment_name.' '.$note->author?->name) }}" class="flex gap-3">
                <span class="grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold {{ $type === \App\Enums\NoteType::Event ? 'bg-brand-100 text-brand-700' : 'bg-ink text-white' }}">{{ strtoupper(mb_substr($note->author?->name ?? 'S', 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <div class="text-xs text-slate-500">{{ $note->created_at->translatedFormat('D j M Y \o\m H:i') }} · {{ $note->author?->name ?? 'Systeem' }}
                        @if ($type !== \App\Enums\NoteType::Event)<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">{{ $type->label() }}</span>@endif</div>
                    <p class="whitespace-pre-line text-sm {{ $type === \App\Enums\NoteType::Event ? 'text-slate-600' : 'mt-1 rounded-lg bg-slate-50 p-3' }}">{{ $note->body }}</p>
                    @if ($note->attachment_path)
                        <a href="{{ route('notes.attachment', $note) }}" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline"><span aria-hidden="true">📎</span> {{ $note->attachment_name }}</a>
                    @endif
                    @if ($canDelete && $type !== \App\Enums\NoteType::Event)
                        <form method="POST" action="{{ route('notes.destroy', $note) }}" onsubmit="return confirm('Dit bericht verwijderen?')" class="mt-1">@csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Verwijderen</button></form>
                    @endif
                </div>
            </li>
        @empty
            <li class="text-sm text-slate-500">Nog niets op de tijdlijn.</li>
        @endforelse
        <li data-timeline-empty class="hidden text-sm text-slate-500">Niets gevonden.</li>
    </ol>
</section>

@props(['notes', 'action'])
<x-card title="Notities" {{ $attributes }}>
    <form method="POST" action="{{ $action }}" class="mb-4">
        @csrf
        <textarea name="body" rows="2" required placeholder="Notitie toevoegen…" class="block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset {{ $errors->has('body') ? 'ring-rose-400' : 'ring-slate-300' }} focus:ring-2 focus:ring-inset focus:ring-brand-600">{{ old('body') }}</textarea>
        @error('body')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        <div class="mt-2 text-right"><button class="rounded-full bg-brand-700 px-4 py-1.5 text-sm font-semibold text-white hover:bg-brand-800">Notitie toevoegen</button></div>
    </form>
    <ul class="space-y-4">
        @forelse ($notes as $note)
            <li class="rounded-lg bg-slate-50 p-3">
                <p class="whitespace-pre-line text-sm">{{ $note->body }}</p>
                <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                    <span>{{ $note->author?->name ?? 'Onbekend' }} · {{ $note->created_at->translatedFormat('d M Y H:i') }}</span>
                    <form method="POST" action="{{ route('notes.destroy', $note) }}" onsubmit="return confirm('Deze notitie verwijderen?')">
                        @csrf @method('DELETE')
                        <button class="text-rose-600 hover:underline">Verwijderen</button>
                    </form>
                </div>
            </li>
        @empty
            <li class="text-sm text-slate-500">Nog geen notities.</li>
        @endforelse
    </ul>
</x-card>

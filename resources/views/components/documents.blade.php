@props(['documents', 'action', 'categories' => null, 'canManage' => true])
<div {{ $attributes->merge(['class' => 'grid min-h-0 gap-4 lg:grid-cols-3']) }} data-documents>
    <x-card title="Documenten ({{ $documents->count() }})" flush class="lg:col-span-2">
        <div class="sticky top-0 z-10 flex flex-wrap gap-2 border-b border-slate-100 bg-white p-3">
            <input type="search" data-doc-q placeholder="Zoeken" class="min-w-0 flex-1 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
            <select data-doc-ext class="rounded-lg border-0 py-1.5 pl-3 pr-8 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
                <option value="">Alle extensies</option><option value="images">Afbeeldingen</option><option value="documents">Documenten</option><option value="spreadsheets">Spreadsheets</option><option value="other">Andere</option>
            </select>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-brand-50 text-left text-xs font-semibold text-slate-700"><tr><th class="px-4 py-2.5">Uploaddatum</th><th class="px-4 py-2.5">Naam</th><th class="px-4 py-2.5">Type document</th><th class="px-4 py-2.5">Aangemaakt door</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($documents as $document)
                <tr data-doc-row data-ext="{{ $document->extensionGroup() }}" data-text="{{ mb_strtolower($document->title.' '.$document->original_name) }}">
                    <td class="whitespace-nowrap px-4 py-2.5">{{ $document->created_at->format('d-m-Y') }}</td>
                    <td class="px-4 py-2.5"><a href="{{ route('documents.show', $document) }}" class="font-medium text-brand-700 hover:underline">{{ $document->title }}</a><div class="text-xs text-slate-500">{{ $document->original_name }} · {{ number_format($document->size / 1024, 0, ',', '.') }} kB</div></td>
                    <td class="px-4 py-2.5">{{ $document->categoryLabel() ?? '—' }}</td>
                    <td class="px-4 py-2.5">{{ $document->uploader?->name ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right">@if ($canManage)<form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Dit document verwijderen?')">@csrf @method('DELETE')<button class="text-xs text-rose-600 hover:underline">Verwijderen</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Klik op "Document toevoegen" om een document toe te voegen.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
    @if ($canManage)
    <x-card title="Document toevoegen">
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            @if ($categories)<x-select name="category" label="Categorie" :options="$categories" />@endif
            <x-field name="title" label="Titel" :required="(bool) $categories" hint="Leeg = bestandsnaam" />
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Bestand</label>
                <input type="file" name="file" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-medium hover:file:bg-slate-200">
                @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500">PDF, afbeelding, Word, Excel of tekst, max. 10 MB.</p></div>
            <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Uploaden</button>
        </form>
    </x-card>
    @endif
</div>

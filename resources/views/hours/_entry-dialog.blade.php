@php
    $input = 'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
    $label = 'mb-1 block text-xs font-medium text-slate-700';
    $serviceOptions = $services->map(fn ($service) => '<option value="'.$service->id.'" data-project="'.$service->project_id.'" data-budget="'.(float) $service->budget_hours.'" data-spent="'.(float) $service->spent_hours.'">'.e($service->name).'</option>')->implode('');
@endphp
<dialog id="entry-dialog" class="m-auto w-[calc(100%-2rem)] max-w-xl rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/40">
    <div class="max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-start justify-between"><h2 class="text-lg font-semibold" data-dialog-title>Uren registreren</h2><button type="button" data-close-dialog class="text-slate-400 hover:text-slate-700" aria-label="Sluiten">✕</button></div>
        <p data-entry-status class="mt-1 hidden text-xs"></p>
        @if ($errors->any() && old('_dialog'))<div class="mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-800 ring-1 ring-rose-200">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('hours.store') }}" data-entry-form data-store-url="{{ route('hours.store') }}" data-time-scope data-service-scope class="mt-4 space-y-3">
            @csrf
            <input type="hidden" name="_dialog" value="1">
            <input type="hidden" name="_method" value="PUT" data-method-field disabled>
            @if ($dialogEmployees)<div><label class="{{ $label }}" for="entry-employee">Medewerker</label><select id="entry-employee" name="employee_id" class="{{ $input }}">@foreach ($dialogEmployees as $id => $name)<option value="{{ $id }}" @selected((string) $id === (string) $defaultEmployeeId)>{{ $name }}</option>@endforeach</select></div>@endif
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}" for="entry-date">Datum</label><input id="entry-date" type="date" name="date" required value="{{ today()->format('Y-m-d') }}" class="{{ $input }}"></div>
                <div><label class="{{ $label }}" for="entry-hours">Totaal uren</label><input id="entry-hours" type="number" name="hours" step="0.25" min="0.25" max="24" data-hours class="{{ $input }}"></div>
            </div>
            <div><label class="{{ $label }}" for="entry-project">Project</label><select id="entry-project" name="project_id" required data-project-select class="{{ $input }}"><option value="">— Kies een project —</option>@foreach ($projects as $project)<option value="{{ $project->id }}">{{ $project->number }} · {{ $project->name }}</option>@endforeach</select></div>
            <div><label class="{{ $label }}" for="entry-service">Dienst</label><select id="entry-service" name="project_service_id" required data-service-select class="{{ $input }}"><option value="">— Kies een dienst —</option>{!! $serviceOptions !!}</select><p data-budget-hint class="mt-1 text-xs text-slate-500"></p></div>
            <div><label class="{{ $label }}" for="entry-type">Urensoort</label><select id="entry-type" name="hour_type_id" class="{{ $input }}"><option value="">— Geen —</option>@foreach ($hourTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}" for="entry-start">Starttijd</label><input id="entry-start" type="time" name="start_time" data-start class="{{ $input }}"></div>
                <div><label class="{{ $label }}" for="entry-end">Eindtijd</label><input id="entry-end" type="time" name="end_time" data-end class="{{ $input }}"></div>
            </div>
            <div><label class="{{ $label }}" for="entry-note">Omschrijving</label><input id="entry-note" name="note" maxlength="255" class="{{ $input }}"></div>
            <div data-locked-only class="hidden"><label class="{{ $label }}" for="entry-reason">Reden van correctie <span class="font-normal text-slate-500">(deze uren zijn al verwerkt voor facturatie)</span></label><input id="entry-reason" name="correction_reason" maxlength="255" class="{{ $input }}"></div>
            <div class="flex justify-end gap-2 pt-1"><button type="button" data-close-dialog class="rounded-full px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Annuleren</button><button data-save-button class="rounded-full bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">Opslaan</button></div>
        </form>

        <div data-edit-only class="mt-5 hidden space-y-4 border-t border-slate-100 pt-4 text-sm">
            <form method="POST" data-action-form="duplicate" class="flex flex-wrap items-end gap-2">@csrf
                <div><label class="{{ $label }}">Dupliceren naar datum</label><input type="date" name="date" class="rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300"></div>
                <button class="rounded-full bg-white px-4 py-1.5 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Dupliceren</button>
            </form>
            <form method="POST" data-action-form="split" data-service-scope class="grid grid-cols-[6rem_1fr_auto] items-end gap-2">@csrf
                <input type="hidden" data-project-select value="">
                <div><label class="{{ $label }}">Splits af (uren)</label><input type="number" name="split_hours" step="0.25" min="0.25" required class="w-full rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300"></div>
                <div><label class="{{ $label }}">Naar dienst</label><select name="project_service_id" required data-service-select class="w-full rounded-lg border-0 bg-white px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">{!! $serviceOptions !!}</select></div>
                <button class="rounded-full bg-white px-4 py-1.5 font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Splitsen</button>
            </form>
            <form method="POST" data-action-form="destroy" onsubmit="return confirm('Deze registratie verwijderen?')" class="flex flex-wrap items-end gap-2">@csrf @method('DELETE')
                <input data-locked-only name="correction_reason" placeholder="Reden van correctie" class="hidden flex-1 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300">
                <button class="text-sm font-medium text-rose-600 hover:underline">Registratie verwijderen</button>
            </form>
        </div>
    </div>
</dialog>

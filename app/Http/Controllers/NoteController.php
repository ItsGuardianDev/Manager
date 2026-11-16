<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NoteController extends Controller
{
    public function storeForOrganization(Request $request, Organization $organization): RedirectResponse
    {
        return $this->store($request, $organization);
    }

    public function storeForPerson(Request $request, Person $person): RedirectResponse
    {
        return $this->store($request, $person);
    }

    public function storeForEmployee(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('addNote', $employee);

        return $this->store($request, $employee, ['employees.show', [$employee, 'tab' => 'timeline']]);
    }

    public function storeForProject(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('projects.edit');

        return $this->store($request, $project, ['projects.show', [$project, 'tab' => 'timeline']]);
    }

    public function attachment(Note $note): StreamedResponse
    {
        $this->authorizeNote($note);

        abort_unless($note->attachment_path, 404);

        return Storage::disk('local')->download($note->attachment_path, $note->attachment_name);
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorizeNote($note);

        abort_if($note->type === NoteType::Event->value, 403, 'Gebeurtenissen kunnen niet worden verwijderd.');

        if ($note->attachment_path) {
            Storage::disk('local')->delete($note->attachment_path);
        }

        $note->delete();

        return back()->with('status', 'Bericht verwijderd.');
    }

    private function authorizeNote(Note $note): void
    {
        if ($note->noteable instanceof Employee) {
            Gate::authorize('viewNotes', $note->noteable);
        }

        if ($note->noteable instanceof Project) {
            Gate::authorize(request()->isMethod('DELETE') ? 'projects.edit' : 'projects.view');
        }
    }

    /**
     * @param  array{0: string, 1: array<int|string, mixed>}|null  $redirect
     */
    private function store(Request $request, Model $noteable, ?array $redirect = null): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', Rule::in(array_keys(NoteType::selectable()))],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx,csv,txt', 'max:10240'],
        ], [], ['body' => 'bericht', 'attachment' => 'bijlage']);

        $attributes = ['body' => $data['body'], 'type' => $data['type'] ?? NoteType::Note->value, 'user_id' => $request->user()->id];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attributes['attachment_path'] = $file->store('note-attachments', 'local');
            $attributes['attachment_name'] = $file->getClientOriginalName();
        }

        $noteable->notes()->create($attributes);

        $back = $redirect ? redirect()->route($redirect[0], $redirect[1]) : back();

        return $back->with('status', 'Bericht toegevoegd.');
    }
}

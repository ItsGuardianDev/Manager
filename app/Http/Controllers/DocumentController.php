<?php

namespace App\Http\Controllers;

use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function storeForEmployee(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageDocuments', $employee);

        return $this->store($request, $employee, redirect()->route('employees.show', [$employee, 'tab' => 'file']), true);
    }

    public function storeForOrganization(Request $request, Organization $organization): RedirectResponse
    {
        return $this->store($request, $organization, redirect()->route('organizations.show', [$organization, 'tab' => 'documents']), false);
    }

    public function storeForPerson(Request $request, Person $person): RedirectResponse
    {
        return $this->store($request, $person, redirect()->route('people.show', [$person, 'tab' => 'documents']), false);
    }

    public function show(Document $document): StreamedResponse
    {
        $this->authorizeDocument($document);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Document $document): RedirectResponse
    {
        $subject = $this->authorizeDocument($document, manage: true);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        $subject->logEvent('Document "'.$document->title.'" verwijderd.');

        return back()->with('status', 'Document verwijderd.');
    }

    private function store(Request $request, Model $subject, RedirectResponse $redirect, bool $categorised): RedirectResponse
    {
        $data = $request->validate([
            'category' => [$categorised ? 'required' : 'nullable', Rule::enum(DocumentCategory::class)],
            'title' => ['nullable', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx,csv,txt', 'max:10240'],
        ], [], ['category' => 'categorie', 'title' => 'titel', 'file' => 'bestand']);

        $file = $request->file('file');
        $title = $data['title'] ?? null ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $subject->documents()->create([
            'category' => $data['category'] ?? null,
            'title' => $title,
            'path' => $file->store('documents/'.class_basename($subject).'/'.$subject->getKey(), 'local'),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $subject->logEvent('Document "'.$title.'" toegevoegd door '.$request->user()->name.'.');

        return $redirect->with('status', 'Document toegevoegd.');
    }

    /**
     * Employee documents follow the HR permissions; CRM documents are open to every signed-in user.
     */
    private function authorizeDocument(Document $document, bool $manage = false): Model
    {
        $subject = $document->documentable;

        if ($subject instanceof Employee) {
            Gate::authorize($manage ? 'manageDocuments' : 'viewDocuments', $subject);
        }

        return $subject;
    }
}

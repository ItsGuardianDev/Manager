<?php

namespace App\Http\Controllers;

use App\Enums\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageDocuments', $employee);

        $data = $request->validate([
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ], [], ['category' => 'categorie', 'title' => 'titel', 'file' => 'bestand']);

        $file = $request->file('file');

        $employee->documents()->create([
            'category' => $data['category'],
            'title' => $data['title'],
            'path' => $file->store('employee-documents/'.$employee->id, 'local'),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('employees.show', [$employee, 'tab' => 'file'])->with('status', 'Document toegevoegd.');
    }

    public function show(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        Gate::authorize('viewDocuments', $employee);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        Gate::authorize('manageDocuments', $employee);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return redirect()->route('employees.show', [$employee, 'tab' => 'file'])->with('status', 'Document verwijderd.');
    }
}

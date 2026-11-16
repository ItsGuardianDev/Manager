<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function destroy(Note $note): RedirectResponse
    {
        $note->delete();

        return back()->with('status', 'Notitie verwijderd.');
    }

    private function store(Request $request, Organization|Person $noteable): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $noteable->notes()->create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('status', 'Notitie toegevoegd.');
    }
}

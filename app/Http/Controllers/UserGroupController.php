<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserGroupController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Permission::AccessManage->value);

        return view('groups.index', [
            'groups' => UserGroup::withCount('users')->orderBy('name')->get(),
            'permissions' => Permission::cases(),
            'users' => User::with(['group', 'employee'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::AccessManage->value);

        UserGroup::create($this->validated($request));

        return back()->with('status', 'Gebruikersgroep aangemaakt.');
    }

    public function update(Request $request, UserGroup $group): RedirectResponse
    {
        Gate::authorize(Permission::AccessManage->value);

        $data = $this->validated($request, $group);
        $this->guardLastAdministrator($group, ! in_array(Permission::AccessManage->value, $data['permissions'], true));

        $group->update($data);

        return back()->with('status', 'Rechten opgeslagen.');
    }

    public function destroy(UserGroup $group): RedirectResponse
    {
        Gate::authorize(Permission::AccessManage->value);

        if ($group->users()->exists()) {
            return back()->withErrors(['group' => 'Deze groep heeft nog gebruikers. Verplaats ze eerst naar een andere groep.']);
        }

        $group->delete();

        return back()->with('status', 'Gebruikersgroep verwijderd.');
    }

    public function assign(Request $request, User $user): RedirectResponse
    {
        Gate::authorize(Permission::AccessManage->value);

        $data = $request->validate(['user_group_id' => ['nullable', 'exists:user_groups,id']]);

        $newGroup = isset($data['user_group_id']) ? UserGroup::find($data['user_group_id']) : null;

        if ($user->group?->grants(Permission::AccessManage) && ! $newGroup?->grants(Permission::AccessManage)) {
            $this->guardLastAdministrator($user->group, true, $user);
        }

        $user->update($data);

        return back()->with('status', 'Gebruiker bijgewerkt.');
    }

    /**
     * Never leave the system without anyone who can manage access.
     */
    private function guardLastAdministrator(UserGroup $group, bool $losingAccessRight, ?User $movingUser = null): void
    {
        if (! $losingAccessRight || ! $group->grants(Permission::AccessManage)) {
            return;
        }

        $otherAdmins = User::where('is_active', true)
            ->whereKeyNot($movingUser?->id ?? 0)
            ->whereHas('group', fn ($query) => $query->whereJsonContains('permissions', Permission::AccessManage->value))
            ->when(! $movingUser, fn ($query) => $query->whereNot('user_group_id', $group->id))
            ->exists();

        if (! $otherAdmins) {
            throw ValidationException::withMessages(['group' => 'Er moet minimaal één actieve gebruiker met het recht “gebruikersgroepen beheren” overblijven.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?UserGroup $group = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('user_groups', 'name')->ignore($group)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(Permission::values())],
        ], [], ['name' => 'naam']);

        $data['permissions'] = array_values($data['permissions'] ?? []);

        return $data;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $this->findUser($request->query('email'), $token);

        return view('auth.invitation', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ], [], ['password' => 'wachtwoord']);

        $user = $this->findUser($data['email'], $token);

        $user->update(['password' => $data['password'], 'invitation_token' => null, 'invited_at' => null, 'is_active' => true]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('employees.index')->with('status', 'Je account is geactiveerd.');
    }

    private function findUser(?string $email, string $token): User
    {
        $user = User::where('email', (string) $email)->where('invitation_token', hash('sha256', $token))->first();

        abort_unless($user && $user->invited_at?->gt(now()->subDays(7)), 404, 'Deze uitnodiging is ongeldig of verlopen.');

        return $user;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Every user can change their own password; new users must do it on first sign-in. */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.password', ['forced' => $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], ['current_password.current_password' => 'The current password is not correct.',
            'password.different' => 'Choose a password different from the current one.']);
        $user = $request->user();
        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->save();
        $request->session()->regenerate();

        return redirect()->to($user->isPi() ? route('pi.home') : route('dashboard'))->with('status', 'Password changed.');
    }
}

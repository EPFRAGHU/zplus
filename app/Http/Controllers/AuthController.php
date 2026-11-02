<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        $log = fn (?int $userId, bool $ok) => LoginEvent::query()->create(['user_id' => $userId, 'email' => $credentials['email'], 'success' => $ok,
            'ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 255)]);

        if (! Auth::attempt($credentials + ['active' => true], $request->boolean('remember'))) {
            $user = User::query()->where('email', $credentials['email'])->first();
            $log($user?->id, false);
            $message = $user && ! $user->active && Hash::check($credentials['password'], $user->password)
                ? 'This account is switched off. Contact the agency.' : 'That email and password do not match an account.';
            throw ValidationException::withMessages(['email' => $message]);
        }
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $log($user->id, true);

        return $user->isPi() ? redirect()->route('pi.home') : redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

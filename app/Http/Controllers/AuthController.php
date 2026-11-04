<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Support\Activity\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        // Per-account lock: 5 wrong passwords for one e-mail locks it for 15 minutes, whichever IP they come from.
        // (The route's throttle:6,1 separately limits each IP.)
        $lockKey = 'login:'.Str::lower($credentials['email']);
        if (RateLimiter::tooManyAttempts($lockKey, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($lockKey) / 60);
            throw ValidationException::withMessages(['email' => "Too many wrong attempts. Try again in {$minutes} minute(s) or ask your admin to reset the password."]);
        }

        if (! Auth::attempt($credentials + ['active' => true])) {   // no "remember me": office PCs are shared
            RateLimiter::hit($lockKey, 15 * 60);
            $user = User::query()->where('email', $credentials['email'])->first();
            $log($user?->id, false);
            Activity::log('sign_in_failed', $user, [], 'E-mail tried: '.$credentials['email'], $user, 'Sign-in');
            $message = $user && ! $user->active && Hash::check($credentials['password'], $user->password)
                ? 'This account is switched off. Contact the agency.' : 'That email and password do not match an account.';
            throw ValidationException::withMessages(['email' => $message]);
        }
        RateLimiter::clear($lockKey);
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $log($user->id, true);
        Activity::log('signed_in', null, [], null, $user, 'Sign-in');

        return $user->isPi() ? redirect()->route('pi.home') : redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Activity::log('signed_out', null, [], null, $request->user(), 'Sign-in');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Tell the browser to drop everything it kept for this site (cached pages and PDFs, cookies, storage).
        return redirect()->route('login')->header('Clear-Site-Data', '"cache", "cookies", "storage"');
    }
}

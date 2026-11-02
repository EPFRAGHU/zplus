<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out switched-off accounts and makes new users set their own password before anything else. */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'This account is switched off. Contact the agency.']);
        }
        if ($user && $user->must_change_password && ! $request->routeIs('account.password', 'account.password.update', 'logout')) {
            return redirect()->route('account.password');
        }

        return $next($request);
    }
}

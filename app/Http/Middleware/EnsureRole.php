<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only the listed roles may use these pages. A PI who reaches an agency page is sent to their own portal. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, $roles, true)) {
            if ($user?->isPi() && ! $request->expectsJson() && $request->isMethod('GET')) {
                return redirect()->route('pi.home');
            }
            abort(403);
        }

        return $next($request);
    }
}

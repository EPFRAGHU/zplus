<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Basic browser security headers on every response. Coolify's proxy does not add them,
 * so the app does. HSTS is only sent over HTTPS (production), never on a local http:// run.
 */
class SecurityHeaders
{
    public const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        ."font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; "
        ."base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // Security audit M1: only this site's own scripts, styles, images and forms; Google Fonts allowed; no plugins,
        // no framing by other sites, no forms posting elsewhere. Inline scripts are still allowed because the pages
        // use inline handlers (e.g. confirm pop-ups); moving them to files would allow removing 'unsafe-inline'.
        $response->headers->set('Content-Security-Policy', self::CSP);
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}

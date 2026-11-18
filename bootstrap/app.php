<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => \Illuminate\Support\Facades\Route::get('/health', \App\Http\Controllers\HealthController::class)->name('health'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind the server's nginx (and Docker): trust its X-Forwarded-* headers so links and cookies use https.
        $middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
        // Security audit L3: answer only for the site's own domain (APP_URL and its subdomains) and the container's own
        // name used by the Docker health check; any other Host header gets "400 Bad Request". Not checked locally or in tests.
        $middleware->trustHosts(at: ['^localhost$', '^127\.0\.0\.1$'], subdomains: true);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\LogAccess::class);
        // Shared PCs: signed-in responses are never cached; a password change signs out every other session.
        $middleware->appendToGroup('web', \App\Http\Middleware\NoStoreWhenSignedIn::class);
        $middleware->appendToGroup('web', \Illuminate\Session\Middleware\AuthenticateSession::class);
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class, 'active' => \App\Http\Middleware\EnsureActiveAccount::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

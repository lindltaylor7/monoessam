<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'check.permission' => \App\Http\Middleware\CheckRoutePermission::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            // AddLinkHeadersForPreloadedAssets quedaba fuera a proposito: generaba una
            // cabecera `Link:` con los ~130 modulepreload de la pagina (>10 KB en staff,
            // sales, headcount y management). El proxy de Hostinger (hcdn) corta la
            // respuesta con un 500 propio cuando una cabecera supera su buffer, y solo
            // ocurria en cargas completas (F5), no en la navegacion Inertia por XHR.
            // Las etiquetas <link rel="modulepreload"> siguen en el <head> via @vite.
            \App\Http\Middleware\PreventInertiaCaching::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function ($response, $exception, $request) {
            if ($response->getStatusCode() === 419) {
                return redirect()->route('login')
                    ->with('message', 'Tu sesión ha expirado, por favor inicia sesión de nuevo.');
            }

            return $response;
        });
    })->create();

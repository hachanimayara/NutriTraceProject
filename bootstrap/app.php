<?php

use App\Http\Middleware\EnsureApproved;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'approved' => EnsureApproved::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->routeIs('login.store')) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Trop de tentatives de connexion. Attendez une minute, puis réessayez.'])
                    ->withInput($request->only('email'));
            }

            return null;
        });
    })->create();

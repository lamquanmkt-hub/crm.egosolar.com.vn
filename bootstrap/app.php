<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('web', [\App\Http\Middleware\LockCompletedFinanceRecords::class]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleWeb::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\UpdateUserLastSeen::class,
            \App\Http\Middleware\EnforcePageAccess::class,
            \App\Http\Middleware\ResolveWorkspaceContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
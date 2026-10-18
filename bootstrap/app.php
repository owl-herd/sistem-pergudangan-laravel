<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php',commands: __DIR__.'/../routes/console.php',health: '/up')
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->remove([Illuminate\Foundation\Http\Middleware\TrimStrings::class,Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class]);
        $middleware->web(prepend:[App\Http\Middleware\WarehouseRequest::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Illuminate\Session\TokenMismatchException $e,Illuminate\Http\Request $request) { return response('Sesi formulir kedaluwarsa. Muat ulang halaman.',419); });
    })->create();

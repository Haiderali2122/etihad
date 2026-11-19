<?php

use App\Http\Controllers\AttendanceSyncController;
use App\Http\Controllers\IclockController;
use App\Http\Middleware\GuardDeletions;
use App\Http\Middleware\ScalarQueryString;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The attendance machine's own addresses: no session, cookies or CSRF, plain text replies.
        then: function (): void {
            Route::prefix('iclock')->group(function (): void {
                Route::match(['get', 'post'], 'cdata', [IclockController::class, 'cdata'])->name('iclock.cdata');
                Route::get('getrequest', [IclockController::class, 'getrequest'])->name('iclock.getrequest');
                Route::post('devicecmd', [IclockController::class, 'devicecmd'])->name('iclock.devicecmd');
            });
            // The attendance reader program on an office computer; it proves itself with the machine's key.
            Route::post('attendance-sync', [AttendanceSyncController::class, 'store'])->middleware('throttle:60,1')->name('attendance-sync.store');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ScalarQueryString::class,
            SetLocale::class,
            GuardDeletions::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

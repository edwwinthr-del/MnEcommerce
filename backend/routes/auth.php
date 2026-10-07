<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Authentication always uses the web session/CSRF stack, even with no Origin header.
// Exclude the conditional Sanctum stack here to avoid applying session middleware twice.
Route::prefix('auth')->middleware(['web', AuthenticateSession::class])
    ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware(['guest:web', 'throttle:register']);
        Route::post('login', [AuthController::class, 'login'])->middleware(['guest:web', 'throttle:login'])->name('login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    });

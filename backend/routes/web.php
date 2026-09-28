<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api/v1/auth')->group(function (): void {
    Route::get('/csrf-token', fn () => response()->json(['data' => ['csrf_token' => csrf_token()]]))
        ->name('api.v1.auth.csrf-token');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.v1.auth.login');
    Route::get('/password/reset/{token}', function (string $token) {
        return response()->json([
            'success' => true,
            'data' => ['token' => $token, 'email' => request()->query('email')],
        ]);
    })->name('password.reset');
    Route::post('/password/forgot', [AuthController::class, 'sendPasswordResetLink'])
        ->middleware('throttle:password-reset')->name('api.v1.auth.password.email');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('api.v1.auth.password.update');

    Route::middleware('auth')->group(function (): void {
        Route::get('/user', [AuthController::class, 'user'])->name('api.v1.auth.user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationNotification'])
            ->middleware('throttle:6,1')->name('api.v1.auth.verification.send');
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    });
});

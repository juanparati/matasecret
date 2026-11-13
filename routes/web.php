<?php

use App\Http\Controllers\SecretController;
use App\Http\Controllers\SecretLinkController;
use App\Http\Controllers\SocialiteAuthController;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[A-Za-z0-9_-]{16,64}');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SocialiteAuthController::class, 'login'])->name('login');
    Route::get('/auth/redirect', [SocialiteAuthController::class, 'redirect'])->name('auth.redirect');
    Route::get('/auth/callback', [SocialiteAuthController::class, 'callback'])->name('auth.callback');
    Route::post('/auth/test', [SocialiteAuthController::class, 'testLogin'])->name('auth.test');
});

// Apple posts its callback from another site. Without the web middleware no session is
// started, so the bounce to the GET callback cannot replace the user's session cookie.
Route::post('/auth/callback', [SocialiteAuthController::class, 'formPostCallback'])
    ->withoutMiddleware('web')
    ->name('auth.callback.form-post');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SocialiteAuthController::class, 'logout'])->name('logout');

    Route::get('/', [SecretController::class, 'create'])->name('secrets.create');
    Route::post('/secrets', [SecretController::class, 'store'])
        ->middleware('throttle:secrets.create')
        ->name('secrets.store');
    Route::delete('/secrets/{id}', [SecretController::class, 'destroy'])->name('secrets.destroy');
    Route::post('/secrets/{id}/email', [SecretLinkController::class, 'store'])->name('secrets.email');

    Route::get('/s/{id}', [SecretController::class, 'show'])->name('secrets.show');
    Route::post('/s/{id}/reveal', [SecretController::class, 'reveal'])
        ->middleware('throttle:secrets.reveal')
        ->name('secrets.reveal');
});

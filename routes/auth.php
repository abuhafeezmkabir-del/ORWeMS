<?php

use App\Http\Controllers\Auth\AuthenticatedPasswordController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| ORWeMS is a controlled humanitarian information system: there is no public
| registration route, and none may be added without an explicit decision by
| WICHOV. Accounts are provisioned by an administrator in a later phase.
|
| Sign-in and password reset are guest-only; signing out requires an
| authenticated session and is deliberately POST-only so it cannot be
| triggered by a third-party page or a prefetched link.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    /*
    |------------------------------------------------------------------------
    | Authenticated password change (Phase 3D)
    |------------------------------------------------------------------------
    |
    | Deliberately reachable when a password has expired: neither route carries
    | the `password.fresh` middleware, so the screen that fixes an expired
    | password is never itself blocked by it. It sits behind `auth` (WHO) and
    | `account.active` (WHETHER) like every other authenticated route.
    |
    | POST, not GET, for the change itself: replacing a credential is a state
    | change and must not be triggerable by a link or a prefetch. CSRF applies
    | on top of that, as it does to every state-changing route.
    |
    | There is deliberately no route that lists, edits or deletes audit records.
    */
    Route::get('change-password', [AuthenticatedPasswordController::class, 'edit'])
        ->name('password.change');

    Route::post('change-password', [AuthenticatedPasswordController::class, 'update'])
        ->name('password.update');
});

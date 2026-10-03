<?php

use App\Http\Controllers\RoleAdministrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Public Landing Page
Route::get('/', function () {
    return view('landing');
})->name('home');

// Application Foundation Shell / Dashboard
//
// Protected from Phase 3A onward. `auth` requires a signed-in operator;
// `account.active` refuses an operator whose account has been disabled, so
// revoking access takes effect immediately rather than at session expiry.
// No role checks yet: the dashboard stays a neutral authenticated landing
// page until Phase 3B establishes authorization.
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'account.active'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Authorization Administration
|--------------------------------------------------------------------------
|
| The ONE place in ORWeMS where privilege is administered.
|
| Middleware order is deliberate and load-bearing, so it is written the same
| way on every route rather than repeated inline:
|
|   auth            WHO is making the request (guest -> redirected to sign in)
|   account.active  WHETHER THEY MAY ACT AT ALL (Phase 3A: a disabled operator
|                   loses their session immediately)
|   permission      WHETHER THEY MAY DO THIS SPECIFIC THING (-> 403)
|
| `account.active` must precede `permission`. In the other order a revoked
| operator would still be told which capabilities their roles carry, and the
| authorization screen would answer for an account that should no longer exist.
|
| The dashboard above deliberately does NOT take a permission. It is a neutral
| authenticated landing page, and inventing a permission to gate it would give
| WICHOV a permission that means nothing. Authorization starts where real work
| starts.
*/

Route::middleware(['auth', 'account.active'])
    ->prefix('administration')
    ->name('administration.')
    ->group(function () {
        // Reading the RBAC configuration is itself sensitive: it discloses which
        // accounts are worth targeting, so it is permissioned rather than left
        // open to every signed-in operator.
        Route::get('/roles', [RoleAdministrationController::class, 'index'])
            ->middleware('permission:roles.view')
            ->name('roles.index');

        Route::get('/permissions', [RoleAdministrationController::class, 'permissions'])
            ->middleware('permission:roles.view')
            ->name('permissions.index');

        // POST, not GET: changing who can do what is a state change, so it must
        // not be triggerable by a link, a prefetch, or a third-party page.
        // CSRF protection applies automatically on top of this.
        Route::post('/roles/{role}/assign', [RoleAdministrationController::class, 'assignToUser'])
            ->middleware('permission:users.assign_roles')
            ->name('roles.assign');
    });


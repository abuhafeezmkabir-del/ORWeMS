<?php

use App\Http\Controllers\BeneficiaryController;
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
// revoking access takes effect immediately rather than at session expiry;
// `password.fresh` (Phase 3D) refuses a session whose password is past the
// configured age — it is a no-op unless ORWEMS_PASSWORD_EXPIRY_ENABLED=true,
// and the change-password route in routes/auth.php is exempt so an expired
// operator can always reach the fix.
// No role checks yet: the dashboard stays a neutral authenticated landing
// page until Phase 3B establishes authorization.
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'account.active', 'password.fresh'])->name('dashboard');

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
|   password.fresh  WHETHER THEIR CREDENTIAL IS STILL CURRENT (Phase 3D; a
|                   no-op unless password expiry is enabled in configuration)
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

Route::middleware(['auth', 'account.active', 'password.fresh'])
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

/*
|--------------------------------------------------------------------------
| Beneficiary Management (Phase 4)
|--------------------------------------------------------------------------
|
| The first real humanitarian workflow: register, view, update, verify and
| archive beneficiaries. The middleware stack on each route is the same
| deliberate order documented above (WHO → WHETHER → FRESHNESS → WHAT), with
| one addition: the controller authorizes per-record through
| BeneficiaryPolicy, which adds WHERE (programme + geographic scope).
|
| Binding resolves by beneficiary_ref (getRouteKeyName on the model is NOT
| used — that would change every implicit binding globally; the `:{column}`
| syntax scopes it to these routes). Internal ids never appear in URLs.
|
| There is deliberately no DELETE route. Archiving (soft-delete) is the
| terminal action, and it runs through a confirm screen + POST.
*/

Route::middleware(['auth', 'account.active', 'password.fresh'])
    ->prefix('beneficiaries')
    ->name('beneficiaries.')
    ->group(function () {
        // Binding must RESOLVE archived rows (`withTrashed`) so the policy's
        // trashed() guards can answer 403: without it, implicit binding on the
        // SoftDeletes model 404s before authorization runs, and "archived
        // records are refused, not missing" would be untestable. The policy
        // still refuses update/verify/archive on trashed rows; only `view`
        // passes, which is correct — archived records are read-only evidence.
        Route::get('/', [BeneficiaryController::class, 'index'])
            ->middleware('permission:beneficiaries.view')
            ->name('index');

        Route::get('/create', [BeneficiaryController::class, 'create'])
            ->middleware('permission:beneficiaries.create')
            ->name('create');

        Route::post('/', [BeneficiaryController::class, 'store'])
            ->middleware('permission:beneficiaries.create')
            ->name('store');

        Route::get('/{beneficiary:beneficiary_ref}', [BeneficiaryController::class, 'show'])
            ->middleware('permission:beneficiaries.view')
            ->withTrashed()
            ->name('show');

        Route::get('/{beneficiary:beneficiary_ref}/edit', [BeneficiaryController::class, 'edit'])
            ->middleware('permission:beneficiaries.update')
            ->withTrashed()
            ->name('edit');

        Route::put('/{beneficiary:beneficiary_ref}', [BeneficiaryController::class, 'update'])
            ->middleware('permission:beneficiaries.update')
            ->withTrashed()
            ->name('update');

        Route::post('/{beneficiary:beneficiary_ref}/verify', [BeneficiaryController::class, 'verify'])
            ->middleware('permission:beneficiaries.verify')
            ->withTrashed()
            ->name('verify');

        Route::get('/{beneficiary:beneficiary_ref}/archive', [BeneficiaryController::class, 'archive'])
            ->middleware('permission:beneficiaries.archive')
            ->withTrashed()
            ->name('archive');

        Route::post('/{beneficiary:beneficiary_ref}/archive', [BeneficiaryController::class, 'confirmArchive'])
            ->middleware('permission:beneficiaries.archive')
            ->withTrashed()
            ->name('archive.confirm');
    });


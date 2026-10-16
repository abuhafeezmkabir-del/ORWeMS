<?php

use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\BeneficiaryRelationshipController;
use App\Http\Controllers\EducationController;
use App\Http\Controllers\HealthRecordController;
use App\Http\Controllers\PlacementController;
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

/*
|--------------------------------------------------------------------------|
| Care, Family & Placement Management (Phase 5)
|--------------------------------------------------------------------------|
|
| Nested under each beneficiary because a placement or a relationship IS
| case content of the child it describes: it has no existence apart from
| the case file, and it inherits that file's scope. The middleware order is
| the same WHO → WHETHER → FRESHNESS → WHAT stack as Phase 4, and the
| `:{column}` binding + withTrashed() mirror it exactly — an archived
| beneficiary's history resolves and is answered by the policy (403 for
| writes, view for reads), never by a 404 that would pretend the record is
| missing.
|
| Nested {placement} / {relationship} bindings additionally check that the
| child in the URL owns the record (404 otherwise): a cross-record id in a
| URL is a mistake, and 403 would confirm the id exists.
|
| There is deliberately no DELETE route — not for placements, not for
| relationships. Closing (a dated, reasoned, audited state change) is the
| terminal action for care history, because care history is evidence.
*/
Route::middleware(['auth', 'account.active', 'password.fresh'])
    ->prefix('beneficiaries')
    ->name('beneficiaries.')
    ->group(function () {
        Route::get('/{beneficiary:beneficiary_ref}/placements', [PlacementController::class, 'index'])
            ->middleware('permission:placements.view')
            ->withTrashed()
            ->name('placements.index');

        Route::get('/{beneficiary:beneficiary_ref}/placements/create', [PlacementController::class, 'create'])
            ->middleware('permission:placements.create')
            ->withTrashed()
            ->name('placements.create');

        Route::post('/{beneficiary:beneficiary_ref}/placements', [PlacementController::class, 'store'])
            ->middleware('permission:placements.create')
            ->withTrashed()
            ->name('placements.store');

        // Change = close current + open next, one POST, one transaction.
        // Gated by placements.create (the opening half); the policy re-checks
        // placements.close on the existing row inside the controller.
        Route::get('/{beneficiary:beneficiary_ref}/placements/change', [PlacementController::class, 'change'])
            ->middleware('permission:placements.create')
            ->withTrashed()
            ->name('placements.change');

        Route::post('/{beneficiary:beneficiary_ref}/placements/change', [PlacementController::class, 'storeChange'])
            ->middleware('permission:placements.create')
            ->withTrashed()
            ->name('placements.change.store');

        Route::get('/{beneficiary:beneficiary_ref}/placements/{placement}/edit', [PlacementController::class, 'edit'])
            ->middleware('permission:placements.update')
            ->withTrashed()
            ->name('placements.edit');

        Route::put('/{beneficiary:beneficiary_ref}/placements/{placement}', [PlacementController::class, 'update'])
            ->middleware('permission:placements.update')
            ->withTrashed()
            ->name('placements.update');

        Route::get('/{beneficiary:beneficiary_ref}/placements/{placement}/close', [PlacementController::class, 'close'])
            ->middleware('permission:placements.close')
            ->withTrashed()
            ->name('placements.close');

        Route::post('/{beneficiary:beneficiary_ref}/placements/{placement}/close', [PlacementController::class, 'confirmClose'])
            ->middleware('permission:placements.close')
            ->withTrashed()
            ->name('placements.close.confirm');

        Route::get('/{beneficiary:beneficiary_ref}/relationships/create', [BeneficiaryRelationshipController::class, 'create'])
            ->middleware('permission:relationships.create')
            ->withTrashed()
            ->name('relationships.create');

        Route::post('/{beneficiary:beneficiary_ref}/relationships', [BeneficiaryRelationshipController::class, 'store'])
            ->middleware('permission:relationships.create')
            ->withTrashed()
            ->name('relationships.store');

        Route::get('/{beneficiary:beneficiary_ref}/relationships/{relationship}/end', [BeneficiaryRelationshipController::class, 'end'])
            ->middleware('permission:relationships.update')
            ->withTrashed()
            ->name('relationships.end');

        Route::post('/{beneficiary:beneficiary_ref}/relationships/{relationship}/end', [BeneficiaryRelationshipController::class, 'confirmEnd'])
            ->middleware('permission:relationships.update')
            ->withTrashed()
            ->name('relationships.end.confirm');
    });

/*
|--------------------------------------------------------------------------|
| Education Management (Phase 6A)
|--------------------------------------------------------------------------|
|
| Nested under each beneficiary because an education enrolment IS case
| content of the child it describes: it has no existence apart from the case
| file, and it inherits that file's scope. The middleware order is the same
| WHO → WHETHER → FRESHNESS → WHAT stack as Phase 4/5, and the `:{column}`
| binding + withTrashed() mirror it exactly — an archived beneficiary's
| history resolves and is answered by the policy (403 for writes, view for
| reads), never by a 404 that would pretend the record is missing.
|
| Nested {enrolment} bindings additionally check that the child in the URL
| owns the record (404 otherwise): a cross-record id in a URL is a mistake,
| and 403 would confirm the id exists.
|
| There is deliberately no DELETE route — not for enrolments. Closing (a
| dated, reasoned, audited state change) is the terminal action for education
| history, because education history is evidence. "Current" is derived from
| end_date IS NULL, never a stored status that could drift.
*/
Route::middleware(['auth', 'account.active', 'password.fresh'])
    ->prefix('beneficiaries')
    ->name('beneficiaries.')
    ->group(function () {
        Route::get('/{beneficiary:beneficiary_ref}/education', [EducationController::class, 'index'])
            ->middleware('permission:education.view')
            ->withTrashed()
            ->name('education.index');

        Route::get('/{beneficiary:beneficiary_ref}/education/create', [EducationController::class, 'create'])
            ->middleware('permission:education.create')
            ->withTrashed()
            ->name('education.create');

        Route::post('/{beneficiary:beneficiary_ref}/education', [EducationController::class, 'store'])
            ->middleware('permission:education.create')
            ->withTrashed()
            ->name('education.store');

        // Change = close current + open next, one POST, one transaction.
        // Gated by education.create (the opening half); the policy re-checks
        // education.close on the existing row inside the controller.
        Route::get('/{beneficiary:beneficiary_ref}/education/change', [EducationController::class, 'change'])
            ->middleware('permission:education.create')
            ->withTrashed()
            ->name('education.change');

        Route::post('/{beneficiary:beneficiary_ref}/education/change', [EducationController::class, 'storeChange'])
            ->middleware('permission:education.create')
            ->withTrashed()
            ->name('education.change.store');

        Route::get('/{beneficiary:beneficiary_ref}/education/{enrolment}/edit', [EducationController::class, 'edit'])
            ->middleware('permission:education.update')
            ->withTrashed()
            ->name('education.edit');

        Route::put('/{beneficiary:beneficiary_ref}/education/{enrolment}', [EducationController::class, 'update'])
            ->middleware('permission:education.update')
            ->withTrashed()
            ->name('education.update');

        Route::get('/{beneficiary:beneficiary_ref}/education/{enrolment}/close', [EducationController::class, 'close'])
            ->middleware('permission:education.close')
            ->withTrashed()
            ->name('education.close');

        Route::post('/{beneficiary:beneficiary_ref}/education/{enrolment}/close', [EducationController::class, 'confirmClose'])
            ->middleware('permission:education.close')
            ->withTrashed()
            ->name('education.close.confirm');
    });

/*
|--------------------------------------------------------------------------
| Restricted Health Records (Phase 6B)
|--------------------------------------------------------------------------
|
| Nested under each beneficiary because a health record IS case content of
| the child it describes: it has no existence apart from the case file, and
| it inherits that file's scope. The middleware order is the same WHO →
| WHETHER → FRESHNESS → WHAT stack as Phase 4/5/6A, and the `:{column}`
| binding + withTrashed() mirror it exactly — an archived beneficiary's
| history resolves and is answered by the policy (403 for writes, view for
| reads), never by a 404 that would pretend the record is missing.
|
| Nested {healthRecord} bindings additionally check that the child in the URL
| owns the record (404 otherwise): a cross-record id in a URL is a mistake,
| and 403 would confirm the id exists.
|
| There is deliberately no DELETE route — not for health records. Closing (a
| dated, reasoned, audited state change) is the terminal action for health
| history, because health history is evidence. "Open" is derived from
| end_date IS NULL, never a stored status that could drift. Several DIFFERENT
| concerns may be open at once; only the exact open duplicate is refused.
|
| PRIVACY: unlike every other family, the health LISTING and the case-file
| section are gated by the same `viewFor` policy question, and the controller
| never loads health rows for an operator the policy refuses. Health data
| never appears in general dashboards, search results, reports, flash
| messages or exception text — only on these routes, for these operators.
*/
Route::middleware(['auth', 'account.active', 'password.fresh'])
    ->prefix('beneficiaries')
    ->name('beneficiaries.')
    ->group(function () {
        Route::get('/{beneficiary:beneficiary_ref}/health', [HealthRecordController::class, 'index'])
            ->middleware('permission:health_records.view')
            ->withTrashed()
            ->name('health.index');

        Route::get('/{beneficiary:beneficiary_ref}/health/create', [HealthRecordController::class, 'create'])
            ->middleware('permission:health_records.create')
            ->withTrashed()
            ->name('health.create');

        Route::post('/{beneficiary:beneficiary_ref}/health', [HealthRecordController::class, 'store'])
            ->middleware('permission:health_records.create')
            ->withTrashed()
            ->name('health.store');

        Route::get('/{beneficiary:beneficiary_ref}/health/{healthRecord}', [HealthRecordController::class, 'show'])
            ->middleware('permission:health_records.view')
            ->withTrashed()
            ->name('health.show');

        Route::get('/{beneficiary:beneficiary_ref}/health/{healthRecord}/edit', [HealthRecordController::class, 'edit'])
            ->middleware('permission:health_records.update')
            ->withTrashed()
            ->name('health.edit');

        Route::put('/{beneficiary:beneficiary_ref}/health/{healthRecord}', [HealthRecordController::class, 'update'])
            ->middleware('permission:health_records.update')
            ->withTrashed()
            ->name('health.update');

        Route::get('/{beneficiary:beneficiary_ref}/health/{healthRecord}/close', [HealthRecordController::class, 'close'])
            ->middleware('permission:health_records.close')
            ->withTrashed()
            ->name('health.close');

        Route::post('/{beneficiary:beneficiary_ref}/health/{healthRecord}/close', [HealthRecordController::class, 'confirmClose'])
            ->middleware('permission:health_records.close')
            ->withTrashed()
            ->name('health.close.confirm');
    });


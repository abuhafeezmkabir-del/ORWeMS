<?php

namespace App\Providers;

use App\Listeners\AuditLoginFailure;
use App\Listeners\AuditLoginLockout;
use App\Listeners\AuditLoginSuccess;
use App\Listeners\AuditLogout;
use App\Listeners\AuditPasswordReset;
use App\Listeners\LogoutOtherSessionsAfterPasswordReset;
use App\Listeners\RecordLastLoginAt;
use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Switch off Laravel's automatic event-listener discovery.
        //
        // Discovery scans app/Listeners and registers every class it finds
        // purely from its `handle()` type-hint — silently, with no line of
        // code anywhere saying so. ORWeMS then registers the SAME listeners
        // explicitly in boot() below, which is where their rationale is
        // written down, and each one ran TWICE per event: two audit rows for
        // every failed sign-in, two sessions destroyed for every password
        // reset. Behaviour that is duplicated rather than wrong is exactly the
        // kind of defect that survives review.
        //
        // Explicit registration wins here over dropping the boot() calls:
        // for security listeners the wiring must be visible in a file someone
        // can read, review and reason about the order of — not inferred from a
        // directory scan. Every listener in app/Listeners is registered below,
        // so nothing is left unwired by turning discovery off.
        //
        // Must run before boot: EventServiceProvider resolves the discovered
        // list inside a `booting` callback, not in register().
        \Illuminate\Foundation\Support\Providers\EventServiceProvider::disableEventDiscovery();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Accountability hooks for Phase 3A. Both listen to framework events
        // rather than to controllers, so any future sign-in or reset path is
        // covered automatically.
        Event::listen(Authenticated::class, RecordLastLoginAt::class);
        Event::listen(PasswordReset::class, LogoutOtherSessionsAfterPasswordReset::class);

        // Phase 3D — audit trail for authentication security events.
        //
        // Registered against the framework's own events rather than against a
        // controller, exactly like the Phase 3A listeners above, so a future
        // sign-in path (console, API guard, social login) is audited without
        // anyone remembering to add a call. The listeners themselves decide
        // what is safe to persist; nothing credential-shaped is forwarded.
        //
        // `Failed` is what records a rejected sign-in, and it fires whether the
        // address was unknown, the password wrong, or the account disabled —
        // three outcomes the operator cannot tell apart, and three an
        // investigator must be able to.
        Event::listen(Login::class, AuditLoginSuccess::class);
        Event::listen(Failed::class, AuditLoginFailure::class);
        Event::listen(Logout::class, AuditLogout::class);
        Event::listen(Lockout::class, AuditLoginLockout::class);
        Event::listen(PasswordReset::class, AuditPasswordReset::class);

        // Phase 3B — bind the permission vocabulary to Laravel's Gate.
        //
        // Every code in the permissions table becomes checkable through the
        // framework's own idioms — $user->can('beneficiaries.view'), the `can:`
        // middleware, @can / @cannot in Blade, $this->authorize() — so the
        // database vocabulary is the single source of truth and a permission
        // added later is usable without editing a hand-written list.
        //
        // Returning `false` for an unheld code is what makes this fail CLOSED:
        // a capability nobody granted is refused rather than passed over, and
        // because the answer is false and not null, no later resolver can reopen
        // it.
        //
        // Two deliberate exclusions:
        //
        //  - Unknown codes return null, not false, so future POLICY abilities
        //    (BeneficiaryPolicy::view and friends) keep working. Only abilities
        //    the system actually recognises as permissions are answered here.
        //  - Nothing is granted for merely being signed in. That is
        //    authentication, settled in Phase 3A by the `auth` and
        //    `account.active` middleware; collapsing the two would make it
        //    impossible to reason about either.
        Gate::before(function (User $user, string $ability): ?bool {
            if (! PermissionCatalog::recognises($ability)) {
                return null;
            }

            return $user->hasPermission($ability);
        });
    }
}

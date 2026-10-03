<?php

namespace App\Providers;

use App\Listeners\LogoutOtherSessionsAfterPasswordReset;
use App\Listeners\RecordLastLoginAt;
use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Auth\Events\Authenticated;
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
        //
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

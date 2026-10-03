<?php

namespace App\Http\Middleware;

use App\Support\Authorization\PermissionCatalog;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the signed-in operator to hold a named permission.
 *
 * Alias: `permission` — declared in bootstrap/app.php alongside `account.active`,
 * and applied as an EXPLICIT alias on the route for the reason documented there:
 * `appendToGroup()` in this Laravel 12 skeleton updates the HTTP Kernel's groups
 * but not the Router's, which is what actually resolves route middleware names,
 * so the middleware would silently never run. An alias is verifiable.
 *
 * Middleware order on a route matters and is deliberate:
 *
 *     ['auth', 'account.active', 'permission:roles.manage']
 *
 * `auth` answers WHO, `account.active` answers WHETHER THEY MAY ACT AT ALL
 * (Phase 3A revokes a disabled operator's session immediately, so a disabled
 * account cannot reach this point and use a privilege its roles still confer),
 * and this middleware answers WHETHER THEY MAY DO THIS. Running the permission
 * check first would let an operator whose access was revoked learn what their
 * roles can do.
 *
 * On denial it throws an AuthorizationException, which Laravel renders as
 * 403 Forbidden. It deliberately does NOT redirect: an unauthorized request
 * must be visibly refused, not quietly sent somewhere that hides the failure.
 * Guests are never handled here — `auth` runs first and redirects them to sign
 * in, so this middleware never has to decide what to tell a non-existent user.
 */
class EnsureUserHasPermission
{
    /**
     * @throws AuthenticationException  when no operator is signed in.
     * @throws AuthorizationException  when the operator lacks the permission.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        // Reached when `auth` was omitted from the route. Refusing is correct:
        // this middleware is a second line of defence, never the first, and a
        // route that forgets `auth` should not quietly become public.
        if ($user === null) {
            throw new AuthenticationException;
        }

        // A misspelled or retired code is a programming error, not a user's
        // mistake. Failing loudly here is what stops a typo such as
        // 'beneficiaries.vew' from looking like an ordinary denial forever.
        foreach ($permissions as $permission) {
            if (! PermissionCatalog::recognises($permission)) {
                throw new \LogicException(sprintf(
                    'The permission [%s] is not defined in the ORWeMS permission vocabulary.',
                    $permission
                ));
            }
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        throw new AuthorizationException(sprintf(
            'This action is unauthorized: [%s] is required.',
            implode(' or ', $permissions)
        ));
    }
}
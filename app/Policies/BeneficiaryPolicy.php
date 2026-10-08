<?php

namespace App\Policies;

use App\Models\Beneficiary;
use App\Models\User;
use App\Support\Authorization\ScopeAuthorization;

/**
 * Who may work with a beneficiary record (Phase 4).
 *
 * Every decision is PERMISSION first, then SCOPE. The permission is Phase 3B
 * (`$user->hasPermission()`); the scope is Phase 3C (`hasScopeFor()`), which
 * narrows and never grants. The two are deliberately kept as separate checks
 * in each method rather than fused into one helper, so a reviewer can see the
 * conjunction rather than trusting a shared abstraction to apply it.
 *
 * DORMANT PERMISSIONS, HONEST 403s. `beneficiaries.view` is a recognised code
 * and its ability throws AuthorizationException when missing (403). A second
 * guard then consults the policy; the policy answers only `Beneficiary`-shaped
 * questions and lets non-beneficiary abilities pass through untouched.
 */
class BeneficiaryPolicy
{
    /**
     * Whether this operator may open the beneficiary listing.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('beneficiaries.view');
    }

    /**
     * Whether this operator may open this beneficiary's detail page.
     */
    public function view(User $user, Beneficiary $beneficiary): bool
    {
        return $user->hasPermission('beneficiaries.view')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * Whether this operator may open the registration form.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('beneficiaries.create');
    }

    /**
     * Whether this operator may open the edit form for this record.
     *
     * Archived records are refused outright: amendment after archiving would
     * silently rewrite the history that archiving is meant to freeze. An
     * archived record is read-only evidence.
     */
    public function update(User $user, Beneficiary $beneficiary): bool
    {
        if ($beneficiary->trashed()) {
            return false;
        }

        return $user->hasPermission('beneficiaries.update')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * Whether this operator may confirm this registration against evidence.
     *
     * Verification is the separation-of-duties action: a distinct permission
     * from `update`, so the person who entered a record cannot confirm their
     * own entry. The controller additionally refuses a second verification.
     */
    public function verify(User $user, Beneficiary $beneficiary): bool
    {
        if ($beneficiary->trashed()) {
            return false;
        }

        return $user->hasPermission('beneficiaries.verify')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * Whether this operator may archive this record.
     */
    public function archive(User $user, Beneficiary $beneficiary): bool
    {
        if ($beneficiary->trashed()) {
            return false;
        }

        return $user->hasPermission('beneficiaries.archive')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }
}

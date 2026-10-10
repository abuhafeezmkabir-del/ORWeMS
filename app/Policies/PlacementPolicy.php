<?php

namespace App\Policies;

use App\Models\Beneficiary;
use App\Models\Placement;
use App\Models\User;
use App\Support\Authorization\ScopeAuthorization;

/**
 * Who may work with a care placement (Phase 5).
 *
 * Same two-step shape as BeneficiaryPolicy — PERMISSION first, then SCOPE,
 * kept as two visible conjunctions rather than one helper, for the same
 * reason: a reviewer must be able to see that neither layer alone grants.
 * The scope is always the scope of the placement's BENEFICIARY: a placement
 * is case content of the child it describes, and it follows the child.
 *
 * The ability names are ordinary (`view`, `create`, `update`, `close`) and
 * are answered by Gate::before only for recognised permission CODES — none
 * of these single words is one — so every check falls through to this
 * policy. `viewAny` and `viewFor` exist because Gate passes the class name
 * for `Gate::allows('view', [Placement::class, $beneficiary])`-style calls
 * as the second argument: `viewFor` takes the BENEFICIARY explicitly, which
 * is what the case-file page needs before any placement rows are loaded.
 */
class PlacementPolicy
{
    /**
     * May this operator list placements for this beneficiary?
     */
    public function viewFor(User $user, Beneficiary $beneficiary): bool
    {
        return $user->hasPermission('placements.view')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * Gate::allows('viewAny', Placement::class) callers — kept for parity
     * with BeneficiaryPolicy; a beneficiary is always the context in Phase 5,
     * so this answers only the coarse question.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('placements.view');
    }

    /**
     * May this operator record a placement for this beneficiary?
     *
     * An archived beneficiary is refused outright: new care records on a
     * frozen case file would contradict what archiving means.
     */
    public function create(User $user, Beneficiary $beneficiary): bool
    {
        if ($beneficiary->trashed()) {
            return false;
        }

        return $user->hasPermission('placements.create')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * May this operator amend this placement?
     *
     * Only while it is ACTIVE. A closed placement is history — changing it
     * would silently rewrite when and where a child was cared for, which is
     * exactly the class of record ORWeMS exists to keep trustworthy.
     */
    public function update(User $user, Placement $placement): bool
    {
        $beneficiary = $placement->beneficiary;

        if ($beneficiary === null || $beneficiary->trashed() || ! $placement->isActive()) {
            return false;
        }

        return $user->hasPermission('placements.update')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * May this operator close this placement?
     *
     * A distinct permission from `update`, so whoever runs the case cannot
     * also decide when it ended without anyone else's capability existing.
     * A placement already closed is refused: closing is not idempotent
     * rewriting, and the controller refuses a second close too.
     */
    public function close(User $user, Placement $placement): bool
    {
        $beneficiary = $placement->beneficiary;

        if ($beneficiary === null || $beneficiary->trashed() || ! $placement->isActive()) {
            return false;
        }

        return $user->hasPermission('placements.close')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }
}
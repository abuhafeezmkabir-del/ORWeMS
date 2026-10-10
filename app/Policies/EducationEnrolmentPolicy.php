<?php

namespace App\Policies;

use App\Models\Beneficiary;
use App\Models\EducationEnrolment;
use App\Models\User;
use App\Support\Authorization\ScopeAuthorization;

/**
 * Who may work with an education enrolment (Phase 6A).
 *
 * Same two-step shape as PlacementPolicy — PERMISSION first, then SCOPE, kept
 * as two visible conjunctions rather than one helper, for the same reason: a
 * reviewer must be able to see that neither layer alone grants. The scope is
 * always the scope of the enrolment's BENEFICIARY: education history is case
 * content of the child it describes, and it follows the child.
 *
 * The ability names are ordinary (`view`, `create`, `update`, `close`) and are
 * answered by Gate::before only for recognised permission CODES — none of these
 * single words is one — so every check falls through to this policy. `viewAny`
 * and `viewFor` exist because Gate passes the class name for
 * `Gate::allows('view', [EducationEnrolment::class, $beneficiary])`-style calls
 * as the second argument: `viewFor` takes the BENEFICIARY explicitly, which is
 * what the case-file page needs before any enrolment rows are loaded.
 */
class EducationEnrolmentPolicy
{
    /**
     * May this operator list education enrolments for this beneficiary?
     */
    public function viewFor(User $user, Beneficiary $beneficiary): bool
    {
        return $user->hasPermission('education.view')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * Gate::allows('viewAny', EducationEnrolment::class) callers — kept for
     * parity with PlacementPolicy; a beneficiary is always the context in
     * Phase 6A, so this answers only the coarse question.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('education.view');
    }

    /**
     * May this operator record an enrolment for this beneficiary?
     *
     * An archived beneficiary is refused outright: new education records on a
     * frozen case file would contradict what archiving means.
     */
    public function create(User $user, Beneficiary $beneficiary): bool
    {
        if ($beneficiary->trashed()) {
            return false;
        }

        return $user->hasPermission('education.create')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * May this operator amend this enrolment?
     *
     * Only while it is CURRENT. A closed enrolment is history — changing it
     * would silently rewrite when and where a child was educated, which is
     * exactly the class of record ORWeMS exists to keep trustworthy.
     */
    public function update(User $user, EducationEnrolment $enrolment): bool
    {
        $beneficiary = $enrolment->beneficiary;

        if ($beneficiary === null || $beneficiary->trashed() || ! $enrolment->isCurrent()) {
            return false;
        }

        return $user->hasPermission('education.update')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * May this operator close this enrolment?
     *
     * A distinct permission from `update`, so whoever runs the case cannot
     * also decide when it ended without anyone else's capability existing.
     * An enrolment already closed is refused: closing is not idempotent
     * rewriting, and the controller refuses a second close too.
     */
    public function close(User $user, EducationEnrolment $enrolment): bool
    {
        $beneficiary = $enrolment->beneficiary;

        if ($beneficiary === null || $beneficiary->trashed() || ! $enrolment->isCurrent()) {
            return false;
        }

        return $user->hasPermission('education.close')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }
}

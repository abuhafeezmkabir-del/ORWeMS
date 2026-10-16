<?php

namespace App\Policies;

use App\Models\Beneficiary;
use App\Models\HealthRecord;
use App\Models\User;
use App\Support\Authorization\ScopeAuthorization;

/**
 * Who may work with a casework-level health record (Phase 6B).
 *
 * Same two-step shape as EducationEnrolmentPolicy — PERMISSION first, then
 * SCOPE, kept as two visible conjunctions rather than one helper, for the
 * same reason: a reviewer must be able to see that neither layer alone
 * grants. The scope is always the scope of the record's BENEFICIARY: health
 * history is case content of the child it describes, and it follows the
 * child.
 *
 * What is STRICTER here than in education, and why:
 *
 *  - The permission family is `health_records.*`, granted deliberately and
 *    separately. `beneficiaries.view`, `education.*`, `reports.view` and the
 *    administrator permissions confer NO health access: a capability from
 *    another family is refused, because health is the most sensitive content
 *    ORWeMS holds and least privilege means the default is no access.
 *  - Viewing a record and viewing the case-file health SECTION are the same
 *    `viewFor` question: the section renders only for operators the policy
 *    answers yes to, and the controller never loads health rows for anyone
 *    else — the data must not reach Blade before the policy has spoken.
 *
 * The ability names are ordinary (`view`, `create`, `update`, `close`) and
 * are answered by Gate::before only for recognised permission CODES — none of
 * these single words is one — so every check falls through to this policy.
 * `viewAny` and `viewFor` exist because Gate passes the class name for
 * `Gate::allows('view', [HealthRecord::class, $beneficiary])`-style calls as
 * the second argument: `viewFor` takes the BENEFICIARY explicitly, which is
 * what the case-file page needs before any health rows are loaded.
 */
class HealthRecordPolicy
{
    /**
     * May this operator list health records for this beneficiary?
     *
     * Also the case-file section question: the section is rendered only when
     * this answers yes, and the controller loads health rows only then.
     */
    public function viewFor(User $user, Beneficiary $beneficiary): bool
    {
        return $user->hasPermission('health_records.view')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * Gate::allows('viewAny', HealthRecord::class) callers — kept for parity
     * with the sibling policies; a beneficiary is always the context in
     * Phase 6B, so this answers only the coarse question.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('health_records.view');
    }

    /**
     * May this operator view this specific record?
     */
    public function view(User $user, HealthRecord $record): bool
    {
        $beneficiary = $record->beneficiary;

        if ($beneficiary === null) {
            return false;
        }

        return $user->hasPermission('health_records.view')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * May this operator record a health record for this beneficiary?
     *
     * An archived beneficiary is refused outright: new health records on a
     * frozen case file would contradict what archiving means.
     */
    public function create(User $user, Beneficiary $beneficiary): bool
    {
        if ($beneficiary->trashed()) {
            return false;
        }

        return $user->hasPermission('health_records.create')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * May this operator amend this record?
     *
     * Only while it is OPEN. A closed record is history — changing it would
     * silently rewrite what was tracked about a child's health, which is
     * exactly the class of record ORWeMS exists to keep trustworthy.
     */
    public function update(User $user, HealthRecord $record): bool
    {
        $beneficiary = $record->beneficiary;

        if ($beneficiary === null || $beneficiary->trashed() || ! $record->isOpen()) {
            return false;
        }

        return $user->hasPermission('health_records.update')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * May this operator close this record?
     *
     * A distinct permission from `update`, so whoever runs the case cannot
     * also decide when tracking ended without anyone else's capability
     * existing. A record already closed is refused: closing is not idempotent
     * rewriting, and the controller refuses a second close too.
     */
    public function close(User $user, HealthRecord $record): bool
    {
        $beneficiary = $record->beneficiary;

        if ($beneficiary === null || $beneficiary->trashed() || ! $record->isOpen()) {
            return false;
        }

        return $user->hasPermission('health_records.close')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }
}

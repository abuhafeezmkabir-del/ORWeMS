<?php

namespace App\Policies;

use App\Models\Beneficiary;
use App\Models\PersonRelationship;
use App\Models\User;
use App\Support\Authorization\ScopeAuthorization;

/**
 * Who may record and end a beneficiary's family/care relationships (Phase 5).
 *
 * Phase 2 built person_relationships as the model (including the exactly-one-
 * related-party CHECK); Phase 5 is the first WORKFLOW over it, and it is
 * beneficiary-anchored: `view` and `create` take the BENEFICIARY as their
 * subject, and `update` (used for ENDing a link, never for rewriting what a
 * relationship was) resolves the beneficiary through the edge's related party.
 *
 * Edges whose related party is only another person — person-to-person links
 * within a household — exist in the model but have no workflow, policy or
 * route yet, so they are refused here rather than reachable with a weaker
 * check. When household-centric management is built it will add its own
 * ability; it must not loosen this one.
 */
class PersonRelationshipPolicy
{
    /**
     * May this operator see this beneficiary's relationship links?
     */
    public function view(User $user, Beneficiary $beneficiary): bool
    {
        return $user->hasPermission('relationships.view')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * May this operator record a new relationship for this beneficiary?
     */
    public function create(User $user, Beneficiary $beneficiary): bool
    {
        if ($beneficiary->trashed()) {
            return false;
        }

        return $user->hasPermission('relationships.create')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }

    /**
     * May this operator end (not rewrite) this relationship?
     *
     * The counterpart edge must be a beneficiary this operator is scoped to;
     * person-only edges are refused (see the class note). A link that already
     * carries an end date is history and is refused — ending twice would
     * rewrite the record of when a family arrangement stopped.
     */
    public function update(User $user, PersonRelationship $relationship): bool
    {
        $beneficiary = $relationship->relatedBeneficiary;

        if ($beneficiary === null
            || $beneficiary->trashed()
            || $relationship->end_date !== null) {
            return false;
        }

        return $user->hasPermission('relationships.update')
            && ScopeAuthorization::allowsBeneficiary($user, $beneficiary);
    }
}
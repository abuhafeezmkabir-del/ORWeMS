<?php

namespace App\Http\Requests\Concerns;

use App\Models\Household;
use App\Models\Person;
use App\Support\Authorization\ScopeAuthorization;

/**
 * Geographic-scope checks shared by the Phase 5 care form requests.
 *
 * A placement names a household and a caregiver — locations and people the
 * operator must be scoped to, or the form becomes a way to attach out-of-
 * area records to a child they may legitimately see. The checks live here,
 * once, so StorePlacementRequest, UpdatePlacementRequest and
 * ChangePlacementRequest cannot drift apart; the SCOPE question itself is
 * still answered only by ScopeAuthorization (Phase 3C), never re-implemented.
 *
 * Deny by default: a household or person without a recorded community has
 * no geography to verify, so it is REFUSED with an explanatory message —
 * not waved through because there was nothing to check.
 */
trait VerifiesCareScope
{
    /**
     * The named household must sit in an area this operator is scoped to.
     *
     * @param  array<string, mixed>  $data
     */
    protected function assertHouseholdInScope(array $data, $validator): void
    {
        if (empty($data['household_id'])) {
            return;
        }

        $household = Household::query()->find($data['household_id']);
        $geography = $household?->community;

        if ($geography === null) {
            $validator->errors()->add(
                'household_id',
                'That household has no recorded location, so its area cannot be verified against your scope.'
            );

            return;
        }

        if (! ScopeAuthorization::allowsScope($this->user(), null, $geography)) {
            $validator->errors()->add(
                'household_id',
                'You are not scoped to the area where that household is located.'
            );
        }
    }

    /**
     * The named person's household must sit in an area this operator is
     * scoped to. A caregiver living outside the operator's area cannot be
     * recorded, even when the placement location itself is inside it: the
     * person is the arrangement, not decoration on it.
     */
    protected function assertPersonInScope(mixed $personId, string $field, $validator): void
    {
        if (empty($personId)) {
            return;
        }

        $person = Person::query()->with('household.community')->find($personId);
        $geography = $person?->household?->community;

        if ($geography === null) {
            $validator->errors()->add(
                $field,
                'That person is not linked to a household with a recorded location, so they cannot be verified against your scope.'
            );

            return;
        }

        if (! ScopeAuthorization::allowsScope($this->user(), null, $geography)) {
            $validator->errors()->add(
                $field,
                'You are not scoped to the area where that person lives.'
            );
        }
    }
}
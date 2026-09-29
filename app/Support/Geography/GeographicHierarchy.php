<?php

namespace App\Support\Geography;

use App\Models\Community;
use App\Models\LocalGovernmentArea;
use App\Models\State;
use App\Models\Ward;
use Illuminate\Validation\ValidationException;

/**
 * Cross-level integrity rules for the ORWeMS geographic hierarchy:
 *
 *   Country → State → LocalGovernmentArea → Ward → Community
 *
 * Foreign keys already guarantee that every referenced row *exists* and that a
 * division cannot be deleted out from under its children. They cannot express
 * "these two columns must describe the same branch of the tree", because
 * `communities` and `beneficiaries` deliberately store several levels side by
 * side (ancestors are denormalised so reporting can filter by state or LGA
 * without four joins).
 *
 * Triggers are intentionally avoided: they are harder to review, behave
 * differently per engine, and are not portable to the SQLite test database.
 * Instead the cross-level rules are enforced on save by the models that own
 * the denormalised columns, and documented in
 * docs/PHASE2_DATABASE_ARCHITECTURE.md.
 *
 * Rules are deliberately permissive about *missing* levels (a beneficiary may
 * only have a state recorded during registration) and strict about
 * *contradictory* levels.
 */
final class GeographicHierarchy
{
    /**
     * A community's local government area must be the one that owns its ward.
     *
     * `communities.ward_id` is nullable and `communities.local_government_area_id`
     * is mandatory, because some settlements have no formal ward. When either
     * side is unknown no contradiction can be asserted, so nothing is checked.
     */
    public static function assertCommunityIsWithinHierarchy(?int $wardId, ?int $localGovernmentAreaId): void
    {
        if ($wardId === null || $localGovernmentAreaId === null) {
            return;
        }

        $wardLgaId = Ward::query()->whereKey($wardId)->value('local_government_area_id');

        if ($wardLgaId !== null && (int) $wardLgaId !== $localGovernmentAreaId) {
            throw ValidationException::withMessages([
                'local_government_area_id' => 'The selected local government area does not contain the selected ward.',
            ]);
        }
    }

    /**
     * Every level supplied on a record must be the true ancestor of the most
     * specific level supplied, walking Community → LGA → State → Country.
     *
     * This allows partially recorded addresses (a state known, the LGA not yet
     * verified) but rejects mixed-branch addresses such as a Nasarawa state
     * paired with an LGA in Niger State.
     */
    public static function assertAddressIsWithinHierarchy(
        ?int $countryId,
        ?int $stateId,
        ?int $localGovernmentAreaId,
        ?int $wardId,
        ?int $communityId,
    ): void {
        $resolvedLgaId = null;

        if ($communityId !== null) {
            $community = Community::query()->find($communityId);

            if ($community !== null) {
                $resolvedLgaId = $community->local_government_area_id;
                $communityWardId = $community->ward_id;

                if ($localGovernmentAreaId !== null && (int) $resolvedLgaId !== $localGovernmentAreaId) {
                    throw ValidationException::withMessages([
                        'local_government_area_id' => 'The selected community is not in the selected local government area.',
                    ]);
                }

                if ($wardId !== null && $communityWardId !== null && (int) $communityWardId !== $wardId) {
                    throw ValidationException::withMessages([
                        'ward_id' => 'The selected community does not belong to the selected ward.',
                    ]);
                }
            }
        }

        if ($wardId !== null) {
            $wardLgaId = Ward::query()->whereKey($wardId)->value('local_government_area_id');

            if ($wardLgaId !== null && $localGovernmentAreaId !== null && (int) $wardLgaId !== $localGovernmentAreaId) {
                throw ValidationException::withMessages([
                    'local_government_area_id' => 'The selected ward is not in the selected local government area.',
                ]);
            }

            $resolvedLgaId ??= $wardLgaId !== null ? (int) $wardLgaId : null;
        }

        if ($localGovernmentAreaId !== null) {
            $resolvedLgaId ??= $localGovernmentAreaId;

            $lgaStateId = LocalGovernmentArea::query()->whereKey($resolvedLgaId)->value('state_id');

            if ($lgaStateId !== null && $stateId !== null && (int) $lgaStateId !== $stateId) {
                throw ValidationException::withMessages([
                    'state_id' => 'The selected local government area is not in the selected state.',
                ]);
            }

            $stateId ??= $lgaStateId !== null ? (int) $lgaStateId : null;
        }

        if ($stateId !== null) {
            $stateCountryId = State::query()->whereKey($stateId)->value('country_id');

            if ($stateCountryId !== null && $countryId !== null && (int) $stateCountryId !== $countryId) {
                throw ValidationException::withMessages([
                    'country_id' => 'The selected state is not in the selected country.',
                ]);
            }
        }
    }
}

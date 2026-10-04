<?php

namespace App\Support\Authorization;

use App\Enums\GeographicScopeLevel;
use App\Enums\ProgrammeStatus;
use App\Models\Community;
use App\Models\LocalGovernmentArea;
use App\Models\Programme;
use App\Models\State;
use App\Models\User;
use App\Models\UserGeographicScope;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Model;

/**
 * The single place ORWeMS decides WHERE an operator's authority applies.
 *
 * Phase 3B answers WHAT an account may do: `User::hasPermission()`. This class
 * answers the separate question of which programme and which area that
 * authority reaches. Keeping them apart is what stops a permission list from
 * having to grow a variant per state — `beneficiaries.view` is one capability,
 * and who may use it where is data, not vocabulary.
 *
 * Two rules shape everything below:
 *
 *  1. SCOPE NARROWS, IT NEVER GRANTS. Every method here is called *after* a
 *     permission has already been confirmed. A scope that matched perfectly
 *     still confers nothing on an account that holds no relevant permission.
 *     This is why `system_administrator` — which resolves accounts and roles —
 *     cannot reach a single child's record by being scoped to every state.
 *
 *  2. DENY BY DEFAULT, STRUCTURALLY. There is no "unrestricted" fallback and no
 *     implicit wildcard. Access requires an active assignment that matches; an
 *     account with no assignments is refused everywhere, and an unknown model,
 *     a missing row and an inactive row are all simply non-matches rather than
 *     special cases that could be mishandled into an allow.
 *
 * No role, account id or email bypasses this. In particular the
 * `super_administrator` role holds every *permission* but is not given implicit
 * scope: a national operator is expressed as a country-level geographic scope
 * plus explicit programme assignments, which is visible in the database and
 * revocable like any other grant.
 *
 * Static by design, matching the project's other pure support classes
 * (`PermissionCatalog`, `GeographicHierarchy`); it holds no state and reads only
 * from the database.
 */
final class ScopeAuthorization
{
    /**
     * May this operator work within this programme?
     *
     * Three conditions, all required: an assignment row exists, it is active,
     * and the programme itself is still operating. A closed or suspended
     * programme is refused even to an operator with a current assignment,
     * because the assignment describes who was responsible, not whether the
     * programme may still be worked on.
     */
    public static function allowsProgramme(User $user, Programme|int $programme): bool
    {
        $programmeId = $programme instanceof Programme ? $programme->getKey() : $programme;

        if ($programmeId === null) {
            return false;
        }

        return $user->programmes()
            ->wherePivot('is_active', true)
            ->where('programmes.id', $programmeId)
            ->where('programmes.status', ProgrammeStatus::Active->value)
            ->exists();
    }

    /**
     * May this operator work within this geographic record?
     *
     * A model that is not part of the geographic hierarchy (a beneficiary, say)
     * cannot be covered by any geographic scope, so it is refused rather than
     * throwing — the caller gets a denial, which is the safe answer.
     */
    public static function allowsGeography(User $user, Model $geography): bool
    {
        $level = GeographicScopeLevel::tryForModel($geography);

        if ($level === null) {
            return false;
        }

        return self::allowsGeographyId($user, $level, (int) $geography->getKey());
    }

    /**
     * May this operator work within the area identified by level and id?
     *
     * The target is re-read from the database rather than trusted as passed in,
     * so a caller cannot hand over a stale or fabricated `is_active` flag. An
     * area that has been deactivated is refused: a scope says an operator may
     * work in an area, not that the area is still in use.
     */
    public static function allowsGeographyId(User $user, GeographicScopeLevel $level, int $id): bool
    {
        $modelClass = $level->modelClass();
        $target = $modelClass::query()->find($id);

        if ($target === null || $target->is_active !== true) {
            return false;
        }

        $held = self::activeScopeKeys($user);

        if ($held === []) {
            return false;
        }

        // The target's own key plus every ancestor's key; a match anywhere in
        // that chain is a grant, which is what makes a state scope cover the
        // LGAs, wards and communities beneath it.
        return array_intersect(self::coveringKeys($level, $id), $held) !== [];
    }

    /**
     * May this operator reach a record that belongs to this programme and this
     * area?
     *
     * The combination rule, stated once so it cannot drift between callers:
     * WHEN BOTH DIMENSIONS APPLY TO A RECORD, BOTH MUST BE HELD. A programme
     * assignment does not widen geographic reach, and a geographic assignment
     * does not widen programme reach. The two are independent filters that both
     * have to pass.
     *
     * Either argument may be omitted when the domain record genuinely has only
     * one dimension. Omitting both is refused: a check with nothing to check is
     * not an "allow everything", it is a caller mistake.
     */
    public static function allowsScope(User $user, ?Programme $programme = null, ?Model $geography = null): bool
    {
        if ($programme === null && $geography === null) {
            return false;
        }

        if ($programme !== null && ! self::allowsProgramme($user, $programme)) {
            return false;
        }

        if ($geography !== null && ! self::allowsGeography($user, $geography)) {
            return false;
        }

        return true;
    }

    /**
     * The scope keys this operator currently holds, as `level:id`.
     *
     * Inactive assignments are excluded here, once, rather than at each call
     * site — revoking an assignment therefore takes effect everywhere at the
     * same moment, with no code path able to forget the filter.
     *
     * @return list<string>
     */
    private static function activeScopeKeys(User $user): array
    {
        return $user->geographicScopes()
            ->where('is_active', true)
            ->get()
            ->map(static fn (UserGeographicScope $scope): string => $scope->level->value.':'.$scope->scopeId())
            ->all();
    }

    /**
     * The target's own scope key, plus every ancestor's.
     *
     * This is the hierarchy, derived rather than stored. A community resolves
     * through its ward to its LGA, state and country; a settlement with no ward
     * resolves straight through its LGA. Because the chain is walked from live
     * geography rows, moving a ward to a different LGA moves every dependent
     * scope decision with it — no re-seeding, no stale copies to reconcile.
     *
     * The chain is built defensively: a missing row simply contributes no key,
     * so an incomplete hierarchy narrows access instead of widening it.
     *
     * @return list<string>
     */
    private static function coveringKeys(GeographicScopeLevel $level, int $id): array
    {
        $keys = [$level->value.':'.$id];
        $lgaId = null;
        $stateId = null;
        $countryId = null;

        switch ($level) {
            case GeographicScopeLevel::Country:
                // The country is the root: nothing above it to add.
                return $keys;

            case GeographicScopeLevel::State:
                $countryId = State::query()->whereKey($id)->value('country_id');
                break;

            case GeographicScopeLevel::Lga:
                $stateId = LocalGovernmentArea::query()->whereKey($id)->value('state_id');
                $countryId = self::stateCountryId($stateId);
                break;

            case GeographicScopeLevel::Ward:
                $lgaId = Ward::query()->whereKey($id)->value('local_government_area_id');
                $stateId = self::lgaStateId($lgaId);
                $countryId = self::stateCountryId($stateId);
                break;

            case GeographicScopeLevel::Community:
                $community = Community::query()->find($id);

                if ($community !== null) {
                    $wardId = $community->ward_id;
                    $lgaId = $community->local_government_area_id;

                    if ($wardId !== null) {
                        // When a community names a ward, the ward is
                        // authoritative for the LGA: it is the branch the
                        // hierarchy actually follows.
                        $lgaId = Ward::query()->whereKey($wardId)->value('local_government_area_id');
                        $keys[] = GeographicScopeLevel::Ward->value.':'.$wardId;
                    }

                    $stateId = self::lgaStateId($lgaId);
                    $countryId = self::stateCountryId($stateId);
                }
                break;
        }

        if ($lgaId !== null) {
            $keys[] = GeographicScopeLevel::Lga->value.':'.$lgaId;
        }

        if ($stateId !== null) {
            $keys[] = GeographicScopeLevel::State->value.':'.$stateId;
        }

        if ($countryId !== null) {
            $keys[] = GeographicScopeLevel::Country->value.':'.$countryId;
        }

        return $keys;
    }

    private static function lgaStateId(int|string|null $lgaId): int|string|null
    {
        return $lgaId === null
            ? null
            : LocalGovernmentArea::query()->whereKey($lgaId)->value('state_id');
    }

    private static function stateCountryId(int|string|null $stateId): int|string|null
    {
        return $stateId === null
            ? null
            : State::query()->whereKey($stateId)->value('country_id');
    }
}

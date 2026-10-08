<?php

namespace App\Support\Authorization;

use App\Enums\GeographicScopeLevel;
use App\Enums\ProgrammeStatus;
use App\Models\Beneficiary;
use App\Models\Community;
use App\Models\LocalGovernmentArea;
use App\Models\Programme;
use App\Models\State;
use App\Models\User;
use App\Models\UserGeographicScope;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Builder;
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
     * May this operator reach this beneficiary record?
     *
     * This is the Phase 4 composition of the two independent Phase 3C checks:
     * the beneficiary's programme AND its geography. A beneficiary is expected
     * to carry both dimensions, and the AND is what stops a Niger operator
     * under programme A from reaching a Nasarawa record under programme A, or
     * a Niger record under programme B. Where the record genuinely has only
     * one dimension (`allowsScope()`), only that dimension is required.
     *
     * The geography check walks ancestors (see `allowsScope()`), so a record
     * deeper than the granted level is IN scope — that is hierarchy, not
     * widening.
     *
     * SCOPE ONLY. This answers WHERE, never WHAT: callers confirm the relevant
     * `beneficiaries.*` permission first (the BeneficiaryPolicy does), and a
     * perfect scope here confers nothing on an account that holds none.
     */
    public static function allowsBeneficiary(User $user, Beneficiary $beneficiary): bool
    {
        return self::allowsScope($user, $beneficiary->programme, $beneficiary->geography());
    }

    /**
     * Restrict a beneficiary query to the records this operator may reach.
     *
     * This is the query half of `allowsBeneficiary()`, and it exists for one
     * reason: the index page must never LOAD an out-of-scope record and merely
     * hide it in the view. Hiding in Blade is a presentation decision; the
     * rows must not leave the database. Both methods therefore share the same
     * expansion logic (`expandedScopeIds()`), so the listing and the detail
     * page cannot disagree about what "in scope" means.
     *
     * SQL shape: the geography predicate matches ANY of the record's
     * geography columns against the corresponding expanded id set
     * (state_id IN states OR lga_id IN lgas OR ...), and the programme
     * predicate ANDs with it. An operator with no live assignments in a
     * dimension gets `[0]` in that dimension's set — a placeholder that matches
     * nothing rather than an empty IN list, which SQL treats unpredictably and
     * which this code must never turn into "match everything".
     *
     * @param  Builder<Beneficiary>  $query
     * @return Builder<Beneficiary>
     */
    public static function applyBeneficiaryScope(User $user, Builder $query): Builder
    {
        $programmeIds = $user->activeProgrammes()->pluck('programmes.id')->all();
        $expanded = self::expandedScopeIds($user);

        return $query
            ->whereIn('programme_id', $programmeIds === [] ? [0] : $programmeIds)
            ->where(function (Builder $query) use ($expanded): void {
                $query->whereIn('country_id', $expanded['countries'])
                    ->orWhereIn('state_id', $expanded['states'])
                    ->orWhereIn('local_government_area_id', $expanded['lgas'])
                    ->orWhereIn('ward_id', $expanded['wards'])
                    ->orWhereIn('community_id', $expanded['communities']);
            });
    }

    /**
     * The expanded id sets, for form dropdowns.
     *
     * Presentation only: it limits the programme/geography OPTIONS shown on
     * the registration form to areas the operator may actually use. The form
     * request re-checks scope on submit, because a dropdown is never a
     * control — this method only saves the operator from choosing something
     * that would then be refused.
     *
     * @return array{countries: list<int>, states: list<int>, lgas: list<int>, wards: list<int>, communities: list<int>}
     */
    public static function expandedScopeIdsForForm(User $user): array
    {
        return self::expandedScopeIds($user);
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
     * Every geographic id this operator's active scopes reach, by level.
     *
     * This is the expansion both `allowsGeography()` and `applyBeneficiaryScope()`
     * read from, which is what keeps the row-level check and the listing query
     * from disagreeing. A scope is expanded DOWNWARD through live geography
     * rows: a state contributes the state itself, every LGA beneath it, and so
     * on to every community. Expansion reads the hierarchy as it stands now,
     * so moving a ward moves every dependent decision with it — no stored
     * copies to go stale.
     *
     * Each set always contains at least the `[0]` placeholder (which matches
     * nothing), for the reason documented on `applyBeneficiaryScope()`.
     *
     * @return array{countries: list<int>, states: list<int>, lgas: list<int>, wards: list<int>, communities: list<int>}
     */
    private static function expandedScopeIds(User $user): array
    {
        $expanded = [
            'countries' => [0],
            'states' => [0],
            'lgas' => [0],
            'wards' => [0],
            'communities' => [0],
        ];

        foreach ($user->activeGeographicScopes()->get() as $scope) {
            $level = $scope->level;
            $id = $scope->scopeId();

            match ($level) {
                GeographicScopeLevel::Country => self::expandCountry($id, $expanded),
                GeographicScopeLevel::State => self::expandState($id, $expanded),
                GeographicScopeLevel::Lga => self::expandLga($id, $expanded),
                GeographicScopeLevel::Ward => self::expandWard($id, $expanded),
                GeographicScopeLevel::Community => self::expandCommunity($id, $expanded),
            };
        }

        return $expanded;
    }

    /**
     * Add one id to one expansion set, de-duplicated.
     *
     * @param  array{countries: list<int>, states: list<int>, lgas: list<int>, wards: list<int>, communities: list<int>}  $expanded
     */
    private static function addExpandedId(array &$expanded, string $set, int|string $id): void
    {
        $id = (int) $id;

        if (! in_array($id, $expanded[$set], true)) {
            $expanded[$set][] = $id;
        }
    }

    /**
     * @param  array{countries: list<int>, states: list<int>, lgas: list<int>, wards: list<int>, communities: list<int>}  $expanded
     */
    private static function expandCountry(int|string $countryId, array &$expanded): void
    {
        self::addExpandedId($expanded, 'countries', $countryId);

        foreach (State::query()->where('country_id', $countryId)->pluck('id') as $stateId) {
            self::expandState($stateId, $expanded);
        }
    }

    /**
     * @param  array{countries: list<int>, states: list<int>, lgas: list<int>, wards: list<int>, communities: list<int>}  $expanded
     */
    private static function expandState(int|string $stateId, array &$expanded): void
    {
        self::addExpandedId($expanded, 'states', $stateId);

        foreach (LocalGovernmentArea::query()->where('state_id', $stateId)->pluck('id') as $lgaId) {
            self::expandLga($lgaId, $expanded);
        }
    }

    /**
     * @param  array{countries: list<int>, states: list<int>, lgas: list<int>, wards: list<int>, communities: list<int>}  $expanded
     */
    private static function expandLga(int|string $lgaId, array &$expanded): void
    {
        self::addExpandedId($expanded, 'lgas', $lgaId);

        foreach (Ward::query()->where('local_government_area_id', $lgaId)->pluck('id') as $wardId) {
            self::expandWard($wardId, $expanded);
        }

        // Settlements with no formal ward resolve through their LGA
        // (Phase 2): they must be picked up here, not only via expandWard().
        foreach (Community::query()->where('local_government_area_id', $lgaId)->pluck('id') as $communityId) {
            self::addExpandedId($expanded, 'communities', $communityId);
        }
    }

    /**
     * @param  array{countries: list<int>, states: list<int>, lgas: list<int>, wards: list<int>, communities: list<int>}  $expanded
     */
    private static function expandWard(int|string $wardId, array &$expanded): void
    {
        self::addExpandedId($expanded, 'wards', $wardId);

        foreach (Community::query()->where('ward_id', $wardId)->pluck('id') as $communityId) {
            self::addExpandedId($expanded, 'communities', $communityId);
        }
    }

    /**
     * @param  array{countries: list<int>, states: list<int>, lgas: list<int>, wards: list<int>, communities: list<int>}  $expanded
     */
    private static function expandCommunity(int|string $communityId, array &$expanded): void
    {
        self::addExpandedId($expanded, 'communities', $communityId);
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

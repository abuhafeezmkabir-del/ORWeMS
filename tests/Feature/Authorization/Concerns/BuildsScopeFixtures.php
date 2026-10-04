<?php

namespace Tests\Feature\Authorization\Concerns;

use App\Enums\ProgrammeStatus;
use App\Models\Community;
use App\Models\Country;
use App\Models\LocalGovernmentArea;
use App\Models\Programme;
use App\Models\State;
use App\Models\Ward;

/**
 * Phase 3C test fixtures.
 *
 * Builds the geographic hierarchy and programme records the scope tests need,
 * with names that describe the branch they belong to so a failing assertion
 * reads as a sentence about a real place rather than about ids.
 *
 * Not a test class itself — the filename deliberately does not end in `Test` so
 * PHPUnit does not try to collect it.
 */
trait BuildsScopeFixtures
{
    protected function makeCountry(string $name = 'Nigeria', string $isoCode = 'NGA'): Country
    {
        return Country::create(['name' => $name, 'iso_code' => $isoCode]);
    }

    protected function makeState(Country $country, string $name): State
    {
        return State::create(['country_id' => $country->getKey(), 'name' => $name]);
    }

    protected function makeLga(State $state, string $name): LocalGovernmentArea
    {
        return LocalGovernmentArea::create(['state_id' => $state->getKey(), 'name' => $name]);
    }

    protected function makeWard(LocalGovernmentArea $lga, string $name): Ward
    {
        return Ward::create(['local_government_area_id' => $lga->getKey(), 'name' => $name]);
    }

    /**
     * A community, optionally attached to a ward.
     *
     * Passing null exercises the settlement-without-a-ward case that Phase 2
     * allows, which resolves through its LGA instead of a ward.
     */
    protected function makeCommunity(LocalGovernmentArea $lga, ?Ward $ward, string $name): Community
    {
        return Community::create([
            'ward_id' => $ward?->getKey(),
            'local_government_area_id' => $lga->getKey(),
            'name' => $name,
        ]);
    }

    protected function makeProgramme(
        string $code = 'WICHOV-PILOT',
        ProgrammeStatus $status = ProgrammeStatus::Active,
    ): Programme {
        return Programme::create([
            'name' => $code.' Programme',
            'code' => $code,
            'status' => $status->value,
        ]);
    }
}

<?php

namespace Tests\Feature\Beneficiaries\Concerns;

use App\Enums\BeneficiaryStatus;
use App\Models\Beneficiary;
use App\Models\Community;
use App\Models\Country;
use App\Models\LocalGovernmentArea;
use App\Models\Programme;
use App\Models\Role;
use App\Models\State;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared fixtures for the Phase 4 beneficiary tests.
 *
 * Builds a two-branch world — Niger branch vs Nasarawa branch, pilot
 * programme vs second programme — so every test can express "right place,
 * wrong programme" and "right programme, wrong place" with real rows.
 * Operator creation is centralised here so scope assignment is never
 * forgotten: a helper grants a role AND the matching scopes in one call.
 */
trait BuildsBeneficiaryFixtures
{
    protected Country $country;

    protected State $niger;

    protected State $nasarawa;

    protected Programme $pilot;

    protected Programme $second;

    protected function setUpFixtures(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->country = Country::create(['name' => 'Nigeria', 'iso_code' => 'NGA']);
        $this->niger = State::create(['country_id' => $this->country->getKey(), 'name' => 'Niger State']);
        $this->nasarawa = State::create(['country_id' => $this->country->getKey(), 'name' => 'Nasarawa State']);

        $this->pilot = Programme::create(['name' => 'WICHOV Pilot', 'code' => 'WICHOV-PILOT', 'status' => 'active']);
        $this->second = Programme::create(['name' => 'Second Programme', 'code' => 'SECOND', 'status' => 'active']);
    }

    /**
     * An operator holding a role plus programme and geographic scope.
     */
    protected function scopedOperator(string $roleCode, Programme $programme, State $state): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('code', $roleCode)->sole());
        $user->assignProgrammeScope($programme);
        $user->assignGeographicScope($state);

        return $user;
    }

    protected function makeBeneficiary(Programme $programme, State $state, array $overrides = []): Beneficiary
    {
        // Geography rows are shared per state: the (state_id, name) unique
        // constraint means a second "Fixture LGA" in the same state must
        // reuse the first, not collide with it.
        $lga = LocalGovernmentArea::firstOrCreate(
            ['state_id' => $state->getKey(), 'name' => 'Fixture LGA']
        );
        $ward = Ward::firstOrCreate(
            ['local_government_area_id' => $lga->getKey(), 'name' => 'Fixture Ward']
        );
        $community = Community::firstOrCreate(
            ['ward_id' => $ward->getKey(), 'local_government_area_id' => $lga->getKey()],
            ['name' => 'Fixture Community']
        );

        return Beneficiary::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'Child',
            'sex' => 'female',
            'programme_id' => $programme->getKey(),
            'country_id' => $this->country->getKey(),
            'state_id' => $state->getKey(),
            'local_government_area_id' => $lga->getKey(),
            'ward_id' => $ward->getKey(),
            'community_id' => $community->getKey(),
            'registration_date' => now()->toDateString(),
            'status' => BeneficiaryStatus::Registered,
        ], $overrides));
    }

    protected function validPayload(Programme $programme, State $state): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Child',
            'sex' => 'female',
            'programme_id' => $programme->getKey(),
            'country_id' => $this->country->getKey(),
            'state_id' => $state->getKey(),
            'registration_date' => now()->toDateString(),
        ];
    }
}

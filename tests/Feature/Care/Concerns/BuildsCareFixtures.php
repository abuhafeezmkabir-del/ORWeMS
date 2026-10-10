<?php

namespace Tests\Feature\Care\Concerns;

use App\Enums\PlacementStatus;
use App\Models\Beneficiary;
use App\Models\CareArrangementType;
use App\Models\Community;
use App\Models\Household;
use App\Models\LocalGovernmentArea;
use App\Models\Person;
use App\Models\Placement;
use App\Models\Programme;
use App\Models\RelationshipType;
use App\Models\State;
use App\Models\Ward;
use Database\Seeders\CareArrangementTypeSeeder;
use Database\Seeders\RelationshipTypeSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Beneficiaries\Concerns\BuildsBeneficiaryFixtures;

/**
 * Phase 5 care fixtures — placements and relationships.
 *
 * Builds on the Phase 4 two-branch world (Niger vs Nasarawa, pilot vs second
 * programme) and adds the care vocabulary: the arrangement-kind table, the
 * relationship-kind table, one in-scope household with a caregiver, and the
 * payload/row helpers every Phase 5 test repeats. The household shares the
 * fixture geography chain (same firstOrCreate keys as makeBeneficiary), so a
 * placement's household and the child's own community are provably the same
 * area — scope tests vary the BRANCH, never the plumbing.
 */
trait BuildsCareFixtures
{
    use BuildsBeneficiaryFixtures;

    protected CareArrangementType $kinship;

    protected RelationshipType $mother;

    protected Household $household;

    protected Person $guardian;

    protected function setUpCareFixtures(): void
    {
        $this->setUpFixtures();
        $this->seed([RelationshipTypeSeeder::class, CareArrangementTypeSeeder::class]);

        $this->kinship = CareArrangementType::where('code', 'kinship_care')->sole();
        $this->mother = RelationshipType::where('code', 'mother')->sole();
        $this->household = $this->makeHousehold($this->niger);
        $this->guardian = Person::create([
            'household_id' => $this->household->getKey(),
            'first_name' => 'Care',
            'last_name' => 'Giver',
            'sex' => 'female',
            'is_guardian' => true,
            'is_caregiver' => true,
        ]);
    }

    /**
     * A household whose community sits in the given state's fixture chain.
     */
    protected function makeHousehold(State $state): Household
    {
        $lga = LocalGovernmentArea::firstOrCreate(['state_id' => $state->getKey(), 'name' => 'Fixture LGA']);
        $ward = Ward::firstOrCreate(['local_government_area_id' => $lga->getKey(), 'name' => 'Fixture Ward']);
        $community = Community::firstOrCreate(
            ['ward_id' => $ward->getKey(), 'local_government_area_id' => $lga->getKey()],
            ['name' => 'Fixture Community']
        );

        return Household::create([
            'household_code' => 'HH-'.Str::upper(Str::random(8)),
            'household_name' => 'Fixture Household',
            'community_id' => $community->getKey(),
        ]);
    }

    /**
     * A beneficiary living in the fixture household (same geography chain).
     */
    protected function housedBeneficiary(Programme $programme, State $state, array $overrides = []): Beneficiary
    {
        return $this->makeBeneficiary($programme, $state, array_merge([
            'household_id' => $this->household->getKey(),
        ], $overrides));
    }

    /**
     * A valid new-placement payload for this officer's world.
     *
     * @return array<string, mixed>
     */
    protected function placementPayload(array $overrides = []): array
    {
        return array_merge([
            'care_arrangement_type_id' => $this->kinship->getKey(),
            'household_id' => $this->household->getKey(),
            'caregiver_person_id' => $this->guardian->getKey(),
            'start_date' => now()->toDateString(),
            'placement_reason' => 'Family could no longer provide care',
            'notes' => null,
        ], $overrides);
    }

    /**
     * Insert an ACTIVE placement row directly (bypassing HTTP), for tests
     * that need history to already exist.
     */
    protected function makePlacement(Beneficiary $beneficiary, array $overrides = []): Placement
    {
        return $beneficiary->placements()->create(array_merge([
            'care_arrangement_type_id' => $this->kinship->getKey(),
            'household_id' => $this->household->getKey(),
            'caregiver_person_id' => $this->guardian->getKey(),
            'start_date' => now()->subDays(7)->toDateString(),
            'placement_reason' => 'Initial placement',
        ], $overrides));
    }

    /**
     * Close a placement directly (model guard path — status derived with it).
     */
    protected function closePlacementDirectly(Placement $placement, ?string $end = null): Placement
    {
        $placement->end_date = Carbon::parse($end ?? now()->toDateString());
        $placement->status = PlacementStatus::Closed;
        $placement->save();

        return $placement->fresh();
    }

    /**
     * A valid record-a-relationship payload pointing at an existing person.
     *
     * @return array<string, mixed>
     */
    protected function relationshipPayload(Person $counterpart, array $overrides = []): array
    {
        return array_merge([
            'relationship_type_id' => $this->mother->getKey(),
            'related_party_type' => 'existing_person',
            'related_person_id' => $counterpart->getKey(),
            'start_date' => now()->toDateString(),
            'notes' => null,
        ], $overrides);
    }
}
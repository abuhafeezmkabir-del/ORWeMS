<?php

namespace Tests\Feature\Education\Concerns;

use App\Models\EducationEnrolment;
use App\Models\EducationLevel;
use App\Models\Beneficiary;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\State;
use Database\Seeders\EducationLevelSeeder;
use Illuminate\Support\Str;
use Tests\Feature\Beneficiaries\Concerns\BuildsBeneficiaryFixtures;

/**
 * Phase 6A education fixtures.
 *
 * Extends the Phase 4 two-branch world (Niger vs Nasarawa, pilot vs second
 * programme) with the education vocabulary: the level catalogue and the
 * payload/row helpers every Phase 6A test repeats. Institutions are named
 * inline per test — they are operator-entered data, never seeded.
 */
trait BuildsEducationFixtures
{
    use BuildsBeneficiaryFixtures;

    protected EducationLevel $primary;

    protected EducationLevel $secondary;

    protected function setUpEducationFixtures(): void
    {
        $this->setUpFixtures();
        $this->seed([EducationLevelSeeder::class]);

        $this->primary = EducationLevel::where('code', 'primary')->sole();
        $this->secondary = EducationLevel::where('code', 'junior_secondary')->sole();
    }

    /**
     * A valid new-enrolment payload for this officer's world.
     *
     * @return array<string, mixed>
     */
    protected function enrolmentPayload(array $overrides = []): array
    {
        return array_merge([
            'education_level_id' => $this->primary->getKey(),
            'institution_name' => 'Fixture Primary School',
            'class_or_year' => 'Primary 4',
            'start_date' => now()->toDateString(),
            'notes' => null,
        ], $overrides);
    }

    /**
     * Insert a CURRENT enrolment row directly (bypassing HTTP), for tests
     * that need history to already exist.
     */
    protected function makeEnrolment(Beneficiary $beneficiary, array $overrides = []): EducationEnrolment
    {
        return $beneficiary->enrolments()->create(array_merge([
            'education_level_id' => $this->primary->getKey(),
            'institution_name' => 'Fixture Primary School',
            'class_or_year' => 'Primary 4',
            'start_date' => now()->subYear()->toDateString(),
            'notes' => 'Initial enrolment',
        ], $overrides));
    }

    /**
     * Close an enrolment directly (model guard path — status derived with it).
     */
    protected function closeEnrolmentDirectly(EducationEnrolment $enrolment, ?string $end = null): EducationEnrolment
    {
        $enrolment->end_date = $end ?? now()->toDateString();
        $enrolment->closure_reason = 'Completed the school year';
        $enrolment->save();

        return $enrolment->fresh();
    }

    /**
     * A structured institution row (the named directory the free-text name
     * can point at).
     */
    protected function makeInstitution(array $overrides = []): Institution
    {
        return Institution::create(array_merge([
            'code' => 'INST-'.Str::upper(Str::random(8)),
            'name' => 'Fixture Academy',
            'is_active' => true,
        ], $overrides));
    }
}

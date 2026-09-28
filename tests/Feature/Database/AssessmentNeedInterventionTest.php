<?php

namespace Tests\Feature\Database;

use App\Enums\InterventionStatus;
use App\Enums\NeedStatus;
use App\Models\Assessment;
use App\Models\Beneficiary;
use App\Models\Intervention;
use App\Models\InterventionType;
use App\Models\Need;
use App\Models\NeedCategory;
use App\Models\Programme;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentNeedInterventionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_beneficiary_can_have_many_assessments_over_time(): void
    {
        $beneficiary = Beneficiary::factory()->create();

        $baseline = Assessment::create([
            'beneficiary_id' => $beneficiary->id,
            'assessment_date' => '2026-01-15',
            'assessment_type' => 'baseline',
        ]);
        $review = Assessment::create([
            'beneficiary_id' => $beneficiary->id,
            'assessment_date' => '2026-06-15',
            'assessment_type' => 'review',
        ]);

        $this->assertCount(2, $beneficiary->assessments);
        $this->assertSame($baseline->id, $beneficiary->assessments->first()->id);
        $this->assertSame($review->id, $beneficiary->assessments->last()->id);
    }

    public function test_assessment_history_and_dates_are_preserved(): void
    {
        $beneficiary = Beneficiary::factory()->create();
        $assessment = Assessment::create([
            'beneficiary_id' => $beneficiary->id,
            'assessment_date' => '2026-02-01',
            'assessor_name' => 'Fixture Assessor',
        ]);

        $assessment->summary = 'Updated summary.';
        $assessment->save();

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'summary' => 'Updated summary.',
        ]);

        $refreshed = $assessment->fresh();

        // The original assessment date survives edits to the record.
        $this->assertSame('2026-02-01', $refreshed->assessment_date->toDateString());
        $this->assertSame('Fixture Assessor', $refreshed->assessor_name);
        $this->assertNotNull($refreshed->updated_at);
    }

    public function test_one_assessment_can_identify_multiple_needs(): void
    {
        $assessment = Assessment::factory()->create();
        $education = NeedCategory::create(['code' => 'education', 'name' => 'Education']);
        $health = NeedCategory::create(['code' => 'health', 'name' => 'Health']);

        Need::create([
            'assessment_id' => $assessment->id,
            'beneficiary_id' => $assessment->beneficiary_id,
            'need_category_id' => $education->id,
            'title' => 'School fees outstanding',
        ]);
        Need::create([
            'assessment_id' => $assessment->id,
            'beneficiary_id' => $assessment->beneficiary_id,
            'need_category_id' => $health->id,
            'title' => 'Routine medical review',
        ]);

        $this->assertCount(2, $assessment->needs);
        $this->assertSame(NeedStatus::Identified, $assessment->needs->first()->status);
        $this->assertTrue($assessment->needs->first()->category->is($education));
        $this->assertCount(2, $assessment->beneficiary->needs);
    }

    public function test_interventions_link_back_to_beneficiary_and_need(): void
    {
        $programme = Programme::create(['name' => 'WICHOV Pilot Programme', 'code' => 'WICHOV-PILOT']);
        $assessment = Assessment::factory()->create(['programme_id' => $programme->id]);
        $category = NeedCategory::create(['code' => 'education', 'name' => 'Education']);
        $need = Need::create([
            'assessment_id' => $assessment->id,
            'beneficiary_id' => $assessment->beneficiary_id,
            'need_category_id' => $category->id,
            'title' => 'School fees outstanding',
        ]);
        $type = InterventionType::create(['code' => 'education_support', 'name' => 'Education Support']);

        $intervention = Intervention::create([
            'beneficiary_id' => $assessment->beneficiary_id,
            'need_id' => $need->id,
            'intervention_type_id' => $type->id,
            'programme_id' => $programme->id,
            'start_date' => '2026-03-01',
            'status' => InterventionStatus::Planned->value,
        ]);

        $intervention->refresh();

        $this->assertTrue($intervention->need->is($need));
        $this->assertTrue($intervention->beneficiary->is($assessment->beneficiary));
        $this->assertTrue($intervention->type->is($type));
        $this->assertTrue($intervention->programme->is($programme));
        $this->assertSame(InterventionStatus::Planned, $intervention->status);
        $this->assertCount(1, $need->interventions);
    }

    public function test_historical_needs_cannot_be_deleted_while_referenced(): void
    {
        $assessment = Assessment::factory()->create();
        $category = NeedCategory::create(['code' => 'health', 'name' => 'Health']);
        $need = Need::create([
            'assessment_id' => $assessment->id,
            'beneficiary_id' => $assessment->beneficiary_id,
            'need_category_id' => $category->id,
            'title' => 'Routine medical review',
        ]);
        $type = InterventionType::create(['code' => 'health_support', 'name' => 'Health Support']);
        Intervention::create([
            'beneficiary_id' => $need->beneficiary_id,
            'need_id' => $need->id,
            'intervention_type_id' => $type->id,
            'status' => InterventionStatus::Planned->value,
        ]);

        $this->expectException(QueryException::class);

        $need->delete();
    }

    public function test_assessments_cannot_be_deleted_while_needs_reference_them(): void
    {
        $assessment = Assessment::factory()->create();
        $category = NeedCategory::create(['code' => 'protection', 'name' => 'Protection']);
        Need::create([
            'assessment_id' => $assessment->id,
            'beneficiary_id' => $assessment->beneficiary_id,
            'need_category_id' => $category->id,
            'title' => 'Protection follow-up',
        ]);

        $this->expectException(QueryException::class);

        $assessment->delete();
    }
}

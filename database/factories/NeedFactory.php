<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Need;
use App\Models\NeedCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TEST / DEVELOPMENT FIXTURES ONLY. Not for production seeding.
 *
 * @extends Factory<Need>
 */
class NeedFactory extends Factory
{
    protected $model = Need::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Both keys resolve to the same assessment, so a need always belongs
        // to the same beneficiary as the assessment that identified it.
        // A memoising resolver keeps this independent of attribute order.
        $assessment = null;
        $resolveAssessment = function () use (&$assessment): Assessment {
            return $assessment ??= Assessment::factory()->create();
        };

        return [
            'assessment_id' => fn () => $resolveAssessment()->id,
            'beneficiary_id' => fn () => $resolveAssessment()->beneficiary_id,
            'need_category_id' => fn () => NeedCategory::firstOrCreate(
                ['code' => 'education'],
                ['name' => 'Education']
            )->id,
            'title' => 'Fixture need',
            'status' => 'identified',
        ];
    }
}

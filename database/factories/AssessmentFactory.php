<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Beneficiary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TEST / DEVELOPMENT FIXTURES ONLY. Not for production seeding.
 *
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'beneficiary_id' => Beneficiary::factory(),
            'assessment_date' => now()->toDateString(),
            'assessment_type' => 'baseline',
            'assessor_name' => 'Fixture Assessor',
        ];
    }
}

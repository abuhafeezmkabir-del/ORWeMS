<?php

namespace Database\Seeders;

use App\Models\InterventionType;
use Illuminate\Database\Seeder;

class InterventionTypeSeeder extends Seeder
{
    /**
     * Baseline intervention types shared across programmes. Seeded with a
     * null programme_id so they are available globally; programme-specific
     * types can be added later without a schema change.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'education_support', 'name' => 'Education Support'],
            ['code' => 'health_support', 'name' => 'Health Support'],
            ['code' => 'nutrition_support', 'name' => 'Nutrition Support'],
            ['code' => 'shelter_support', 'name' => 'Shelter Support'],
            ['code' => 'wash_support', 'name' => 'WASH Support'],
            ['code' => 'psychosocial_support', 'name' => 'Psychosocial Support'],
            ['code' => 'protection_support', 'name' => 'Protection Support'],
            ['code' => 'livelihood_support', 'name' => 'Livelihood Support'],
            ['code' => 'legal_support', 'name' => 'Legal and Documentation Support'],
            ['code' => 'cash_transfer', 'name' => 'Cash Transfer'],
            ['code' => 'other', 'name' => 'Other'],
        ];

        foreach ($types as $type) {
            InterventionType::updateOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name'], 'programme_id' => null, 'is_active' => true]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\NeedCategory;
use Illuminate\Database\Seeder;

class NeedCategorySeeder extends Seeder
{
    /**
     * Baseline child-welfare need domains. Generic reference data used to
     * classify needs identified during assessments. Editable data, not
     * hard-coded business rules.
     */
    public function run(): void
    {
        $categories = [
            ['code' => 'education', 'name' => 'Education'],
            ['code' => 'health', 'name' => 'Health'],
            ['code' => 'nutrition', 'name' => 'Nutrition'],
            ['code' => 'shelter', 'name' => 'Shelter and Housing'],
            ['code' => 'wash', 'name' => 'Water, Sanitation and Hygiene'],
            ['code' => 'protection', 'name' => 'Protection'],
            ['code' => 'psychosocial', 'name' => 'Psychosocial Support'],
            ['code' => 'livelihood', 'name' => 'Household Livelihood'],
            ['code' => 'legal_documentation', 'name' => 'Legal and Documentation'],
            ['code' => 'other', 'name' => 'Other'],
        ];

        foreach ($categories as $category) {
            NeedCategory::updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'is_active' => true]
            );
        }
    }
}

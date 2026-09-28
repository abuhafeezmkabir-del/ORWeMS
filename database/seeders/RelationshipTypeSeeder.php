<?php

namespace Database\Seeders;

use App\Models\RelationshipType;
use Illuminate\Database\Seeder;

class RelationshipTypeSeeder extends Seeder
{
    /**
     * Generic household relationship vocabulary. These are neutral reference
     * labels (no real people) and are maintained as data, not hard-coded
     * strings, so the vocabulary can be extended without a schema change.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'mother', 'name' => 'Mother'],
            ['code' => 'father', 'name' => 'Father'],
            ['code' => 'guardian', 'name' => 'Guardian'],
            ['code' => 'caregiver', 'name' => 'Caregiver'],
            ['code' => 'sibling', 'name' => 'Sibling'],
            ['code' => 'grandmother', 'name' => 'Grandmother'],
            ['code' => 'grandfather', 'name' => 'Grandfather'],
            ['code' => 'aunt', 'name' => 'Aunt'],
            ['code' => 'uncle', 'name' => 'Uncle'],
            ['code' => 'relative', 'name' => 'Other Relative'],
            ['code' => 'household_head', 'name' => 'Household Head'],
            ['code' => 'other', 'name' => 'Other'],
        ];

        foreach ($types as $type) {
            RelationshipType::updateOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name'], 'is_active' => true]
            );
        }
    }
}

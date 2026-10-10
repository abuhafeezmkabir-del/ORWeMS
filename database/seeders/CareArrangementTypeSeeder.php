<?php

namespace Database\Seeders;

use App\Models\CareArrangementType;
use Illuminate\Database\Seeder;

/**
 * Phase 5 — the care arrangement vocabulary.
 *
 * REFERENCE DATA ONLY, same contract as the permission catalogue: this says
 * which arrangement kinds EXIST, not which placements use them. The list is
 * the vocabulary WICHOV's pilot needs to describe alternative care (the
 *UNCRC/Alternative Care framework's shapes, kept plain); adding a kind later
 * is a seeded row, not a migration, and `is_active` withdraws a kind from new
 * selection without touching placements that already carry it.
 *
 * Idempotent by construction (code-keyed updateOrCreate): re-seeding
 * converges rather than duplicating, and a row an administrator has
 * deactivated stays deactivated.
 */
class CareArrangementTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::types() as $type) {
            $existing = CareArrangementType::where('code', $type['code'])->first();

            CareArrangementType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'is_active' => $existing?->is_active ?? true,
                ]
            );
        }
    }

    /**
     * The arrangement kinds ORWeMS recognises.
     *
     * @return list<array{code: string, name: string, description: string}>
     */
    public static function types(): array
    {
        return [
            [
                'code' => 'kinship_care',
                'name' => 'Kinship care',
                'description' => 'Living with relatives or other persons connected by the family or community.',
            ],
            [
                'code' => 'foster_care',
                'name' => 'Foster care',
                'description' => 'Living with a vetted, supported foster family outside the biological family.',
            ],
            [
                'code' => 'residential_care',
                'name' => 'Residential care',
                'description' => 'Living in a residential facility or institution.',
            ],
            [
                'code' => 'guardian_at_law',
                'name' => 'Guardian (non-relative)',
                'description' => 'Living with an appointed guardian who is not a relative.',
            ],
            [
                'code' => 'independent_living',
                'name' => 'Independent living',
                'description' => 'A young person living independently or in supported semi-independent accommodation.',
            ],
            [
                'code' => 'reunified',
                'name' => 'Reunified with family',
                'description' => 'Returned to and living with the biological family or community of origin.',
            ],
        ];
    }
}
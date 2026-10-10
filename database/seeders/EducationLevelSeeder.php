<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

/**
 * Phase 6A — the education level vocabulary.
 *
 * REFERENCE DATA ONLY, same contract as the permission catalogue and the care
 * arrangement vocabulary: this says which levels EXIST, not which enrolments use
 * them. The list is the vocabulary WICHOV's pilot needs to describe a child's
 * schooling (the shapes most sub-Saharan systems use, kept plain); adding a
 * level later is a seeded row, not a migration, and `is_active` withdraws a
 * level from new selection without touching enrolments that already carry it.
 *
 * Idempotent by construction (code-keyed updateOrCreate): re-seeding converges
 * rather than duplicating, and a row an administrator has deactivated stays
 * deactivated.
 */
class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::levels() as $level) {
            $existing = EducationLevel::where('code', $level['code'])->first();

            EducationLevel::updateOrCreate(
                ['code' => $level['code']],
                [
                    'name' => $level['name'],
                    'sort_order' => $level['sort_order'],
                    'description' => $level['description'],
                    'is_active' => $existing?->is_active ?? true,
                ]
            );
        }
    }

    /**
     * The education levels ORWeMS recognises, in the order they are studied.
     *
     * @return list<array{code: string, name: string, sort_order: int, description: string}>
     */
    public static function levels(): array
    {
        return [
            [
                'code' => 'early_childhood',
                'name' => 'Early childhood / pre-primary',
                'sort_order' => 10,
                'description' => 'Playgroup, nursery or pre-primary, before formal primary schooling.',
            ],
            [
                'code' => 'primary',
                'name' => 'Primary',
                'sort_order' => 20,
                'description' => 'Primary or elementary school (the first formal stage).',
            ],
            [
                'code' => 'junior_secondary',
                'name' => 'Junior secondary',
                'sort_order' => 30,
                'description' => 'Junior secondary / junior high (e.g. JSS 1–3).',
            ],
            [
                'code' => 'senior_secondary',
                'name' => 'Senior secondary',
                'sort_order' => 40,
                'description' => 'Senior secondary / senior high (e.g. SSS 1–3).',
            ],
            [
                'code' => 'tertiary',
                'name' => 'Tertiary',
                'sort_order' => 50,
                'description' => 'University, polytechnic or college-level study.',
            ],
            [
                'code' => 'vocational',
                'name' => 'Vocational / skills',
                'sort_order' => 60,
                'description' => 'Vocational training or skills acquisition outside the academic ladder.',
            ],
            [
                'code' => 'non_formal',
                'name' => 'Non-formal / alternative',
                'sort_order' => 70,
                'description' => 'Non-formal or accelerated education, including second-chance programmes.',
            ],
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\HealthConcern;
use Illuminate\Database\Seeder;

/**
 * Phase 6B — the health concern vocabulary.
 *
 * REFERENCE DATA ONLY, same contract as the permission catalogue, the care
 * arrangement vocabulary and the education levels: this says which concerns
 * EXIST, not which records use them. The list is the CASEWORK-level vocabulary
 * WICHOV's pilot needs to describe a child's health tracking (what is being
 * followed up, and what kind of attention it needs) — deliberately coarse,
 * never diagnoses, never clinical detail.
 *
 * Idempotent by construction (code-keyed updateOrCreate): re-seeding converges
 * rather than duplicating, and a row an administrator has deactivated stays
 * deactivated.
 */
class HealthConcernSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::concerns() as $concern) {
            $existing = HealthConcern::where('code', $concern['code'])->first();

            HealthConcern::updateOrCreate(
                ['code' => $concern['code']],
                [
                    'name' => $concern['name'],
                    'sort_order' => $concern['sort_order'],
                    'description' => $concern['description'],
                    'is_active' => $existing?->is_active ?? true,
                ]
            );
        }
    }

    /**
     * The health concerns ORWeMS recognises, in display order.
     *
     * @return list<array{code: string, name: string, sort_order: int, description: string}>
     */
    public static function concerns(): array
    {
        return [
            [
                'code' => 'general_wellbeing',
                'name' => 'General wellbeing',
                'sort_order' => 10,
                'description' => 'Routine monitoring of a child\u2019s general health and wellbeing.',
            ],
            [
                'code' => 'injury_or_wound',
                'name' => 'Injury or wound',
                'sort_order' => 20,
                'description' => 'A physical injury or wound being followed up until healed.',
            ],
            [
                'code' => 'ongoing_illness',
                'name' => 'Ongoing illness',
                'sort_order' => 30,
                'description' => 'An illness requiring repeated follow-up or monitoring.',
            ],
            [
                'code' => 'referral_follow_up',
                'name' => 'Referral follow-up',
                'sort_order' => 40,
                'description' => 'A referral to a health facility or service, tracked until resolved.',
            ],
            [
                'code' => 'immunisation',
                'name' => 'Immunisation',
                'sort_order' => 50,
                'description' => 'Immunisation status being followed up (appointments, records).',
            ],
            [
                'code' => 'nutrition',
                'name' => 'Nutrition',
                'sort_order' => 60,
                'description' => 'Nutritional support or monitoring being followed up.',
            ],
            [
                'code' => 'other_concern',
                'name' => 'Other concern',
                'sort_order' => 70,
                'description' => 'A casework-level concern not covered above; describe it in the summary.',
            ],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * TEST / DEVELOPMENT FIXTURES ONLY. Not for production seeding.
 *
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = (string) config('orwems.household_code_prefix', 'HH');

        return [
            'household_code' => $prefix.'-'.Str::upper(Str::random(10)),
            'household_name' => 'Fixture Household',
            'registration_date' => now()->toDateString(),
        ];
    }
}

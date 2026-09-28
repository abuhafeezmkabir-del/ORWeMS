<?php

namespace Database\Factories;

use App\Enums\Sex;
use App\Models\Household;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TEST / DEVELOPMENT FIXTURES ONLY. Not for production seeding.
 *
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'first_name' => 'Fixture',
            'last_name' => 'Adult-'.fake()->unique()->numerify('####'),
            'sex' => fake()->randomElement([Sex::Female, Sex::Male]),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
        ];
    }
}

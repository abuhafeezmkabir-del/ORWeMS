<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TEST / DEVELOPMENT FIXTURES ONLY.
 *
 * Generates obviously synthetic operator accounts. It must never be used to
 * seed a real WICHOV environment, and the generated identities are not real
 * people. There is no public registration path in ORWeMS.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Fixture Operator',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => UserStatus::Active,
            'password_changed_at' => now(),
            'last_login_at' => null,
        ];
    }

    /**
     * An account that is refused at sign-in and whose sessions are terminated.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Disabled,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * Retained for completeness. ORWeMS does not enforce email verification in
     * Phase 3A (see docs/PHASE3A_AUTHENTICATION_FOUNDATION.md), so this state
     * currently has no effect on authentication.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

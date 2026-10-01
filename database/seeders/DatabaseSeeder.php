<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only verified reference/reference-like data is seeded here:
     * no beneficiaries, households, assessments or interventions are
     * fabricated. Every seeder is idempotent, so `php artisan db:seed`
     * can be executed repeatedly without creating duplicates.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            PilotGeographySeeder::class,
            ProgrammeSeeder::class,
            RelationshipTypeSeeder::class,
            NeedCategorySeeder::class,
            InterventionTypeSeeder::class,
        ]);

        // Local development convenience account only.
        //
        // This is NOT a production credential and must never be: it is created
        // only when the environment is explicitly `local` or `testing`, it
        // carries no real identity, and its password is the conventional
        // throwaway fixture value. WICHOV operator accounts are provisioned by
        // an administrator in a later phase — there is no public registration.
        if (app()->environment(['local', 'testing'])) {
            User::firstOrCreate(
                ['email' => 'test@example.com'],
                ['name' => 'Test User', 'password' => 'password']
            );
        }
    }
}


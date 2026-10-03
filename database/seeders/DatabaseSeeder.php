<?php

namespace Database\Seeders;

use App\Models\Role;
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
        // RBAC vocabulary comes first: RolePermissionSeeder resolves the role
        // and permission codes it maps, so both catalogues must already exist.
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);

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
            $user = User::firstOrCreate(
                ['email' => 'test@example.com'],
                ['name' => 'Test User', 'password' => 'password']
            );

            // Phase 3B: the development account needs a role, or the
            // authorization screens would be unreachable and local testing of
            // them would always 403.
            //
            // The grant is an ordinary role_user row — the same mechanism, and
            // the same database constraints, as any other assignment. There is
            // no special-cased account, no hard-coded ID and no bypass in the
            // authorization code: remove this row and the account holds exactly
            // what its role grants, which is nothing.
            //
            // The environment guard is the only thing between this fixture and a
            // production database, so it is worth stating plainly: no other
            // seeder assigns any role to any account, in any environment. WICHOV's
            // real first administrator is provisioned deliberately, through the
            // console, on a named account.
            $user->assignRole(Role::where('code', 'super_administrator')->sole());
        }
    }
}


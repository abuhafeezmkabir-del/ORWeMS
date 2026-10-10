<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Programme;
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
            // Phase 5 — the care arrangement vocabulary (reference data).
            CareArrangementTypeSeeder::class,
            // Phase 6A — the education level vocabulary (reference data).
            // Institutions are deliberately NOT seeded (see Institution model).
            EducationLevelSeeder::class,
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

            // Phase 3C: the development account also needs an explicit access
            // scope, because scope is never implicit and is never conferred by a
            // role. Without this the local administrator would hold every
            // permission and still reach no programme and no area — which is the
            // correct production behaviour, and merely inconvenient in
            // development.
            //
            // A country-level geographic scope covers every state, LGA, ward and
            // community beneath it, so one row is enough for the pilot. These are
            // still ordinary scope rows: same tables, same constraints, same
            // environment guard as the role assignment above, and removable with
            // a single UPDATE. There is no special-cased account and no bypass in
            // the authorization code — revoke these rows and the account reaches
            // nothing at all.
            //
            // WICHOV's real operators are scoped deliberately by an administrator
            // in a later phase; no other seeder grants scope to any account, in
            // any environment.
            $user->assignProgrammeScope(Programme::where('code', 'WICHOV-PILOT')->sole());
            $user->assignGeographicScope(Country::where('iso_code', 'NGA')->sole());
        }
    }
}


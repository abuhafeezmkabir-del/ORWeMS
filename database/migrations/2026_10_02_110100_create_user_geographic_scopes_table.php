<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3C — geographic access scope (one row per granted area).
 *
 * A row says: this operator may work within this area. Like programme scope it
 * is a FILTER, never a grant — it narrows what a role's permissions reach and
 * cannot create a capability on its own.
 *
 * WHY FIVE REAL FOREIGN KEYS INSTEAD OF ONE POLYMORPHIC PAIR
 *
 * The obvious generic design is `scope_type` + `scope_id`. It is rejected here.
 * `scope_id` could not carry a foreign key, so nothing at the database level
 * would stop a scope row pointing at a deleted state, a nonexistent LGA, or an
 * id that happens to mean different things in different tables after a restore.
 * For a table that decides who may read children's records, that is the wrong
 * trade. Five nullable, individually constrained columns keep a real foreign key
 * on every level, and the `level` column makes the intended target explicit
 * rather than inferred from a class name string.
 *
 * WHY ONE UNIQUE INDEX PER LEVEL
 *
 * "The same operator may hold the same area once" is a per-level rule. A single
 * composite unique index over all five nullable columns would NOT enforce it,
 * because MySQL and SQLite both treat NULL as distinct in a unique index — two
 * identical state scopes would be accepted. Five indexes, each (user_id, level
 * column), enforce it exactly: the column that is set is compared, the four that
 * are null do not collide, and a duplicate row is refused by the engine.
 *
 * DELETION POLICY
 *
 * RESTRICT on every foreign key, matching Phase 3B and Phase 2.1. Geography is
 * reference data that is deactivated rather than deleted, so RESTRICT blocks
 * only accidental destruction — and it refuses to let the record of which
 * operator could reach which area vanish with a stray DELETE. RESTRICT is also
 * required for the CHECK constraint below to be legal on MySQL: a CHECK is
 * refused on a column governed by a cascading referential action.
 */
return new class extends Migration
{
    private const LEVEL_TARGET_CHECK = 'user_geographic_scopes_level_target_check';

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    /**
     * Exactly one level column is populated, and it is the one the `level`
     * column names.
     *
     * Two failure modes share one expression: a row with no target at all (a
     * scope that silently grants nothing anywhere) and a row with two targets (a
     * scope whose true extent is ambiguous). The `level` value is part of every
     * branch so the column and the target cannot drift apart into a state the
     * application would misread.
     */
    private function levelTargetExpression(): string
    {
        $columns = [
            'country' => 'country_id',
            'state' => 'state_id',
            'lga' => 'local_government_area_id',
            'ward' => 'ward_id',
            'community' => 'community_id',
        ];

        $parts = [];

        foreach ($columns as $level => $column) {
            $conditions = ["level = '{$level}'", "{$column} IS NOT NULL"];

            foreach ($columns as $otherLevel => $otherColumn) {
                if ($otherLevel !== $level) {
                    $conditions[] = "{$otherColumn} IS NULL";
                }
            }

            $parts[] = '('.implode(' AND ', $conditions).')';
        }

        return '('.implode(' OR ', $parts).')';
    }

    public function up(): void
    {
        Schema::create('user_geographic_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Stable machine-readable level: country | state | lga | ward |
            // community. See App\Enums\GeographicScopeLevel.
            $table->string('level', 20);

            // Exactly one of these five is non-null on any row.
            $table->foreignId('country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->restrictOnDelete();
            $table->foreignId('local_government_area_id')->nullable()->constrained('local_government_areas')->restrictOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->restrictOnDelete();
            $table->foreignId('community_id')->nullable()->constrained('communities')->restrictOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One assignment per operator per area, enforced at each level.
            $table->unique(['user_id', 'country_id'], 'ugs_user_country_unique');
            $table->unique(['user_id', 'state_id'], 'ugs_user_state_unique');
            $table->unique(['user_id', 'local_government_area_id'], 'ugs_user_lga_unique');
            $table->unique(['user_id', 'ward_id'], 'ugs_user_ward_unique');
            $table->unique(['user_id', 'community_id'], 'ugs_user_community_unique');

            $table->index('level');
            $table->index(['user_id', 'is_active']);
        });

        if (! $this->isMysql()) {
            // SQLite cannot add a CHECK constraint to a table through ALTER
            // TABLE, and cannot drop one either. The identical invariant is
            // enforced on that driver by App\Models\UserGeographicScope::booted()
            // and is covered by tests on both engines.
            return;
        }

        // Laravel 12 has no Blueprint::check(), so the DDL is written directly —
        // the same approach Phase 2.1 used for person_relationships.
        DB::statement(
            'ALTER TABLE user_geographic_scopes ADD CONSTRAINT '.self::LEVEL_TARGET_CHECK
            .' CHECK ('.$this->levelTargetExpression().')'
        );
    }

    public function down(): void
    {
        // The CHECK constraint is dropped with the table on MySQL; no separate
        // constraint drop is needed and none would work on SQLite.
        Schema::dropIfExists('user_geographic_scopes');
    }
};

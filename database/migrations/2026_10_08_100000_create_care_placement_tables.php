<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — care arrangement reference data and the placement history model.
 *
 * Two tables, because WHAT a placement is and WHICH arrangement it took are
 * different questions:
 *
 *   care_arrangement_types  reference data (kinship care, foster care, residential
 *                           care, ...). A seeded, flag-disabled table rather than
 *                           an enum column, so programme directors can add an
 *                           arrangement without a schema change and existing
 *                           placements keep their historical label.
 *
 *   placements              the append-only history of where a child has lived
 *                           and who cared for them. Rows are created and CLOSED,
 *                           never deleted: care history is evidence, and there is
 *                           no delete permission or destroy route anywhere in
 *                           ORWeMS. Closing sets end_date; "current" is derived
 *                           from end_date IS NULL, never stored twice.
 *
 * THE SINGLE-ACTIVE CONSTRAINT is enforced twice, deliberately:
 *
 *   1. the database: a VIRTUAL generated column `active_flag` holds 1 while
 *      end_date is NULL and NULL afterwards, and a UNIQUE index on
 *      (beneficiary_id, active_flag) refuses a second row with flag 1 — while
 *      any number of closed rows (flag NULL) are allowed, because UNIQUE
 *      ignores NULLs. Verified against SQLite 3.39 (the test driver) and MySQL.
 *   2. the model (Placement::booted): to turn a driver error into a readable
 *      validation message, and to cover drivers lacking generated columns.
 *
 * CHECK constraints cannot be expressed with the Blueprint fluent API in this
 * Laravel version (Blueprint::check() does not exist), so the MySQL checks are
 * guarded raw statements below — the same pattern Phase 2.1 used for
 * person_relationships. SQLite cannot add a CHECK to an existing table, so
 * behaviour there comes from the model guards; the generated-column constraint
 * above IS enforced on SQLite, which is the one that must never be bypassable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_arrangement_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('care_arrangement_type_id')
                ->constrained('care_arrangement_types')
                ->restrictOnDelete();
            // The arrangement's location: nullOnDelete, because removing a
            // household must never remove the child's care history — only the
            // link to a household that no longer exists. The row survives.
            $table->foreignId('household_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('caregiver_person_id')
                ->nullable()
                ->constrained('persons')
                ->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 10)->default('active');
            $table->string('placement_reason', 255)->nullable();
            $table->string('closure_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Generated AFTER end_date exists (declaration order matters):
            // 1 while open, NULL once closed — the single-active switch.
            $table->unsignedTinyInteger('active_flag')
                ->virtualAs('CASE WHEN end_date IS NULL THEN 1 END');

            $table->unique(
                ['beneficiary_id', 'active_flag'],
                'placements_beneficiary_active_unique'
            );
            $table->index(['beneficiary_id', 'start_date'], 'placements_beneficiary_history_index');
        });

        // MySQL-only, driver-guarded (constraintExists() skips elsewhere):
        // logical dates and the status/end_date pairing must hold at the
        // database level too, so no code path — raw SQL included — can write
        // a placement that ended before it started or claims to be active
        // while carrying an end date. SQLite gets the same rules from the
        // model guards in Placement::booted().
        if ($this->constraintExists('placements', 'placements_dates_check')) {
            DB::statement(
                'ALTER TABLE placements ADD CONSTRAINT placements_dates_check '
                .'CHECK (end_date IS NULL OR end_date >= start_date)'
            );
        }

        if ($this->constraintExists('placements', 'placements_status_check')) {
            DB::statement(
                'ALTER TABLE placements ADD CONSTRAINT placements_status_check '
                ."CHECK ((status = 'active' AND end_date IS NULL) "
                ."OR (status = 'closed' AND end_date IS NOT NULL))"
            );
        }
    }

    public function down(): void
    {
        // Dropping the table drops its indexes and MySQL CHECK constraints
        // with it; the guarded statements above are only ever additive.
        Schema::dropIfExists('placements');
        Schema::dropIfExists('care_arrangement_types');
    }

    /**
     * Does this constraint NOT already exist? (true → safe to add.)
     *
     * Mirrors Phase 2.1's constraint_exists() so re-running or re-applying
     * migrations cannot fail on a duplicate constraint.
     */
    private function constraintExists(string $table, string $constraint): bool
    {
        try {
            $rows = DB::select(
                'SELECT COUNT(*) AS c FROM information_schema.table_constraints '
                ."WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ?",
                [$table, $constraint]
            );
        } catch (\Throwable) {
            // Not MySQL (or no information_schema): nothing to guard against,
            // and nothing to add either — the statements above are MySQL SQL.
            return false;
        }

        return (int) ($rows[0]->c ?? 0) === 0;
    }
};
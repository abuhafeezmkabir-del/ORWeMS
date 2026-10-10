<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6A — education reference data and the enrolment history model.
 *
 * Three tables, because WHAT a child studies, WHERE, and the HISTORY of it are
 * different questions:
 *
 *   education_levels      configurable reference data (nursery, primary, JSS,
 *                         SSS, tertiary, ...). A seeded, flag-disabled table
 *                         rather than an enum column — the "configurable
 *                         reference data ... rather than hard-coding business
 *                         rules in enums" the brief asks for.
 *
 *   institutions          an OPTIONAL structured reference to a school. Left
 *                         deliberately UNSEEDED: institutions are real-world
 *                         places a programme works with, and seeding them
 *                         would fabricate programme data. The enrolment always
 *                         records the school by NAME (see below).
 *
 *   education_enrolments  the append-only history of where a child has studied
 *                         and since when. Rows are created and CLOSED, never
 *                         deleted: education history is evidence, and there is
 *                         no delete permission or destroy route anywhere.
 *                         Closing sets end_date; "current" is end_date IS NULL.
 *
 * TWO DESIGN DECISIONS WORTH STATING PLAINLY:
 *
 *  1. STATUS IS DERIVED, NOT STORED (and not a PHP enum). "Current enrolment
 *     status" is exactly `end_date IS NULL`. A stored status column would be a
 *     second source of truth that could contradict the dates; a configurable
 *     status VOCABULARY would let an administrator configure two rows as
 *     "current" — the very contradiction the invariant exists to prevent. So
 *     the invariant is structural: one current enrolment per child.
 *
 *  2. THE INSTITUTION NAME IS THE SOURCE OF TRUTH. institution_name is NOT
 *     NULL and survives independently of the optional institution_id link, so
 *     renaming or removing an institution row can never erase what a
 *     caseworker recorded about a child's schooling.
 *
 * THE SINGLE-CURRENT CONSTRAINT is enforced twice, deliberately (the same
 * shape Phase 5 uses for placements):
 *
 *   1. the database: a VIRTUAL generated column `active_flag` holds 1 while
 *      end_date is NULL and NULL afterwards, and a UNIQUE index on
 *      (beneficiary_id, active_flag) refuses a second row with flag 1 — while
 *      any number of closed rows (flag NULL) are allowed, because UNIQUE
 *      ignores NULLs. Verified against SQLite 3.39 (the test driver) and MySQL.
 *   2. the model (EducationEnrolment::booted): to turn a driver error into a
 *      readable validation message, and to cover drivers lacking generated
 *      columns.
 *
 * CHECK constraints cannot be expressed with the Blueprint fluent API in this
 * Laravel version, so the MySQL date check is a guarded raw statement below —
 * the same pattern Phase 2.1 and Phase 5 used. SQLite cannot add a CHECK to an
 * existing table, so behaviour there comes from the model guards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 100);
            // Levels are naturally ordered (nursery before primary before SSS);
            // storing the order keeps the form dropdown honest without a
            // hard-coded list in PHP.
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('education_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('education_level_id')
                ->constrained('education_levels')
                ->restrictOnDelete();
            // The structured institution link is OPTIONAL and drops cleanly:
            // removing an institution must never remove a child's education
            // history — only the link. The recorded NAME below survives.
            $table->foreignId('institution_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            // The school as the caseworker recorded it. Always present, so the
            // history is legible even when no institution row exists.
            $table->string('institution_name', 255);
            // e.g. "Primary 3", "JSS 2", "Year 1" — the class/grade or year of
            // study. Free text on purpose: education systems name this
            // differently and a closed vocabulary would not fit every context.
            $table->string('class_or_year', 100)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('closure_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Generated AFTER end_date exists (declaration order matters):
            // 1 while current, NULL once closed — the single-current switch.
            $table->unsignedTinyInteger('active_flag')
                ->virtualAs('CASE WHEN end_date IS NULL THEN 1 END');

            $table->unique(
                ['beneficiary_id', 'active_flag'],
                'education_enrolments_beneficiary_active_unique'
            );
            $table->index(['beneficiary_id', 'start_date'], 'education_enrolments_beneficiary_history_index');
        });

        // MySQL-only, driver-guarded (constraintExists() skips elsewhere):
        // dates cannot invert, so no code path — raw SQL included — can write
        // an enrolment that ended before it began. SQLite gets the same rule
        // from the model guard in EducationEnrolment::booted().
        if ($this->constraintExists('education_enrolments', 'education_enrolments_dates_check')) {
            DB::statement(
                'ALTER TABLE education_enrolments ADD CONSTRAINT education_enrolments_dates_check '
                .'CHECK (end_date IS NULL OR end_date >= start_date)'
            );
        }
    }

    public function down(): void
    {
        // Dropping the table drops its indexes and MySQL CHECK constraint with
        // it; the guarded statement above is only ever additive.
        Schema::dropIfExists('education_enrolments');
        Schema::dropIfExists('institutions');
        Schema::dropIfExists('education_levels');
    }

    /**
     * Does this constraint NOT already exist? (true → safe to add.)
     *
     * Mirrors Phase 2.1/5's constraint_exists() so re-running or re-applying
     * migrations cannot fail on a duplicate constraint.
     */
    private function constraintExists(string $table, string $constraint): bool
    {
        try {
            $rows = DB::select(
                'SELECT COUNT(*) AS c FROM information_schema.table_constraints '
                .'WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
                [$table, $constraint]
            );
        } catch (\Throwable) {
            // Not MySQL (or no information_schema): nothing to guard against,
            // and nothing to add either — the statement above is MySQL SQL.
            return false;
        }

        return (int) ($rows[0]->c ?? 0) === 0;
    }
};


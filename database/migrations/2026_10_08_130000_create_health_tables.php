<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6B — a deliberately minimal, normalized health-record foundation.
 *
 * Health data is the most sensitive content ORWeMS will hold, so this table
 * is narrower than the education enrolments it is shaped on:
 *
 *   health_records  the append-only history of a child's casework-level
 *                   health records. Rows are created and CLOSED, never
 *                   deleted: health history is evidence, and there is no
 *                   delete permission or destroy route anywhere.
 *                   Closing sets end_date; "open" is end_date IS NULL.
 *
 * WHAT IS DELIBERATELY ABSENT — and why:
 *
 *  - NO diagnosis, prescription, medication, lab, biometric or attachment
 *    columns. This phase is a casework-level record (what happened, who needs
 *    to act next), not a clinical EMR. Each such field is a new privacy
 *    surface; adding one later is an additive migration plus review.
 *  - NO stored status column and NO single-current invariant. Unlike an
 *    enrolment (one school at a time), a child can legitimately carry several
 *    OPEN records at once. A single-current UNIQUE here would forbid real
 *    casework, so there is none. What is refused instead is the exact
 *    duplicate: same child, same concern, still open.
 *  - NO enum for the concern. The vocabulary is configurable reference data
 *    (health_concerns, in this same migration) rather than a PHP enum, for
 *    the same reason Phase 6A keeps education levels in a table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_concerns', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'health_concerns_active_index');
        });

        $this->createHealthRecords();
    }

    public function down(): void
    {
        Schema::dropIfExists('health_records');
        Schema::dropIfExists('health_concerns');
    }

    private function createHealthRecords(): void
    {
        Schema::create('health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('health_concern_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('summary', 500);
            $table->date('record_date');
            $table->date('end_date')->nullable();
            $table->string('closure_reason', 255)->nullable();
            $table->string('follow_up_status', 16)->default('none');
            $table->string('referral_to', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unsignedTinyInteger('open_flag')
                ->virtualAs('CASE WHEN end_date IS NULL THEN 1 END');

            $table->unique(
                ['beneficiary_id', 'health_concern_id', 'open_flag'],
                'health_records_beneficiary_concern_open_unique'
            );
            $table->index(['beneficiary_id', 'record_date'], 'health_records_beneficiary_history_index');
        });

        if ($this->constraintMissing('health_records', 'health_records_dates_check')) {
            DB::statement(
                'ALTER TABLE health_records ADD CONSTRAINT health_records_dates_check '
                .'CHECK (end_date IS NULL OR end_date >= record_date)'
            );
        }
    }

    private function constraintMissing(string $table, string $constraint): bool
    {
        try {
            $rows = DB::select(
                'SELECT COUNT(*) AS c FROM information_schema.table_constraints '
                .'WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
                [$table, $constraint]
            );
        } catch (\Throwable) {
            return false;
        }

        return (int) ($rows[0]->c ?? 0) === 0;
    }
};

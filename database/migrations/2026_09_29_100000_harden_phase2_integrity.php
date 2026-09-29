<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.1 integrity hardening.
 *
 * Two genuine gaps were found while reviewing the Phase 2 schema. They are
 * corrected in this new migration so that the already-applied Phase 2
 * migrations stay byte-for-byte unchanged and the migration history is intact.
 *
 * 1. beneficiaries.person_id had a foreign key but no uniqueness, so two
 *    unrelated beneficiaries could claim the same household-roster identity.
 * 2. person_relationships allowed both related columns to be NULL, or both to
 *    be populated, producing meaningless relationship edges.
 *
 * All DDL is guarded so the migration can be re-run safely after an
 * interrupted attempt, and down() restores the exact Phase 2 state.
 */
return new class extends Migration
{
    private const RELATED_PARTY_CHECK = 'person_relationships_related_party_check';

    private const BENEFICIARY_PERSON_UNIQUE = 'beneficiaries_person_id_unique';

    private const RELATED_PERSON_FK = 'person_relationships_related_person_id_foreign';

    private const RELATED_BENEFICIARY_FK = 'person_relationships_related_beneficiary_id_foreign';

    private const BENEFICIARY_PERSON_FK = 'beneficiaries_person_id_foreign';

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function constraintExists(string $table, string $name, string $type): bool
    {
        return DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $name, $type]
        )->aggregate > 0;
    }

    private function dropForeignKey(string $table, string $constraint): void
    {
        if ($this->constraintExists($table, $constraint, 'FOREIGN KEY')) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }

    public function up(): void
    {
        // 1. A person may be the household-roster identity of at most one
        //    beneficiary. NULL stays allowed: most beneficiaries have no
        //    roster row at all.
        if (! Schema::hasIndex('beneficiaries', self::BENEFICIARY_PERSON_UNIQUE)) {
            Schema::table('beneficiaries', function (Blueprint $table) {
                $table->unique('person_id', self::BENEFICIARY_PERSON_UNIQUE);
            });
        }

        if (! $this->isMysql()) {
            // SQLite cannot add a CHECK constraint to an existing table and
            // cannot drop a foreign key. The identical invariant is enforced on
            // that driver by App\Models\PersonRelationship::booted().
            return;
        }

        // 2. MySQL refuses a CHECK constraint on any column carrying a cascading
        //    foreign-key action (error 3823), so the two related-party columns
        //    are switched from ON DELETE SET NULL to ON DELETE RESTRICT first.
        //
        //    This is also the correct behaviour for ORWeMS: SET NULL would have
        //    silently emptied the target of a relationship when a person or
        //    beneficiary was hard-deleted. Persons and beneficiaries are
        //    soft-deleted, so RESTRICT only blocks accidental destructive
        //    deletes and never interferes with normal operation.
        $this->dropForeignKey('person_relationships', self::RELATED_PERSON_FK);
        $this->dropForeignKey('person_relationships', self::RELATED_BENEFICIARY_FK);

        Schema::table('person_relationships', function (Blueprint $table) {
            $table->foreign('related_person_id')->references('id')->on('persons')->restrictOnDelete();
            $table->foreign('related_beneficiary_id')->references('id')->on('beneficiaries')->restrictOnDelete();
        });

        // 3. A relationship must reference either a related person or a related
        //    beneficiary — never both, never neither. Laravel 12 has no
        //    Blueprint::check(), so the DDL is written directly.
        if (! $this->constraintExists('person_relationships', self::RELATED_PARTY_CHECK, 'CHECK')) {
            DB::statement(
                'ALTER TABLE person_relationships ADD CONSTRAINT '.self::RELATED_PARTY_CHECK
                .' CHECK ('
                .'(related_person_id IS NULL AND related_beneficiary_id IS NOT NULL)'
                .' OR '
                .'(related_person_id IS NOT NULL AND related_beneficiary_id IS NULL)'
                .')'
            );
        }
    }

    public function down(): void
    {
        if ($this->isMysql()) {
            if ($this->constraintExists('person_relationships', self::RELATED_PARTY_CHECK, 'CHECK')) {
                DB::statement('ALTER TABLE person_relationships DROP CONSTRAINT '.self::RELATED_PARTY_CHECK);
            }

            $this->dropForeignKey('person_relationships', self::RELATED_PERSON_FK);
            $this->dropForeignKey('person_relationships', self::RELATED_BENEFICIARY_FK);

            Schema::table('person_relationships', function (Blueprint $table) {
                $table->foreign('related_person_id')->references('id')->on('persons')->nullOnDelete();
                $table->foreign('related_beneficiary_id')->references('id')->on('beneficiaries')->nullOnDelete();
            });
        }

        if (! Schema::hasIndex('beneficiaries', self::BENEFICIARY_PERSON_UNIQUE)) {
            return;
        }

        // InnoDB uses this unique index to satisfy the foreign key on
        // person_id, so the constraint is dropped first, the unique index
        // removed, and the foreign key re-created in its Phase 2 form
        // (ON DELETE SET NULL). Dropping the index first fails with
        // "Cannot drop index ... needed in a foreign key constraint".
        $this->dropForeignKey('beneficiaries', self::BENEFICIARY_PERSON_FK);

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropUnique(self::BENEFICIARY_PERSON_UNIQUE);
        });

        if ($this->isMysql()) {
            Schema::table('beneficiaries', function (Blueprint $table) {
                $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
            });
        }
    }
};

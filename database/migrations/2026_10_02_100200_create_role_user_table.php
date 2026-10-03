<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3B — user-to-role assignment (many-to-many).
 *
 * Deletion policy: RESTRICT on both foreign keys, deliberately.
 *
 * ORWeMS does not delete users — an account is disabled (Phase 3A) — and does
 * not delete roles; they are deactivated. So a RESTRICT here should never fire
 * in normal operation. It exists so that if either record is ever destroyed by
 * a script, a migration mistake or a future feature, the database refuses
 * rather than silently erasing the record of who held which responsibility.
 *
 * A CASCADE would be the tidier-looking choice and is exactly why it is wrong:
 * it would let one DELETE quietly strip every role from every account, and the
 * evidence that an operator had administrative access would disappear with it.
 * Removing an assignment must be a deliberate, individually auditable act.
 *
 * `created_at` records when the assignment was made, so the seed data is never
 * mistaken for a fresh grant when the pivots are read during an investigation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            // Database-enforced uniqueness: the same role cannot be attached to
            // the same account twice. Application-level checks are a
            // convenience; this is the guarantee.
            $table->unique(['user_id', 'role_id'], 'role_user_user_role_unique');

            // The unique index above already serves lookups by user_id, but not
            // by role_id (leftmost-prefix rule), which the Role -> users
            // relationship needs.
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
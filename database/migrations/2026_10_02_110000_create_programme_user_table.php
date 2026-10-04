<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3C — programme access scope (many-to-many).
 *
 * A row here says: this operator may work within this programme. It is the
 * WHERE half of authorization; Phase 3B's permissions are the WHAT half, and
 * the two are kept in separate tables on purpose. Nothing in this table grants
 * an capability — it only narrows the capabilities a role already confers. That
 * direction matters: scope is a filter, never a grant, so adding a scope row to
 * an account that holds no beneficiary permission still lets that account reach
 * no beneficiary record.
 *
 * Deletion policy: RESTRICT on both foreign keys, for the same reason as
 * `role_user` in Phase 3B. ORWeMS does not delete users (they are disabled) and
 * does not delete programmes (they are closed). A RESTRICT therefore blocks
 * only an accidental destructive delete, and refuses to let the record of which
 * operator was assigned to which programme disappear silently.
 *
 * `is_active` revokes access without erasing the assignment. Withdrawing an
 * operator from a programme is an accountability event that may need reviewing
 * later, so it is recorded rather than deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_id')->constrained('programmes')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The same operator cannot be assigned to the same programme twice.
            // Application checks are a convenience; this is the guarantee.
            $table->unique(['user_id', 'programme_id'], 'programme_user_user_programme_unique');

            // Serves Programme -> users (the unique index above is prefixed by
            // user_id, so it cannot serve lookups by programme alone).
            $table->index('programme_id');

            // The authorization query filters on both of these together.
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_user');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3B — roles.
 *
 * A role names a RESPONSIBILITY (job function), never a place. Programme,
 * state, LGA, ward and community scope are deliberately absent: they answer
 * "where may this responsibility be applied", which is Phase 3C. Keeping the
 * two concepts in separate tables is what lets Phase 3C add scope without
 * rewriting authorization.
 *
 * `is_active` rather than deletion. A role that was once granted to real WICHOV
 * accounts is an accountability record: it must be withdrawable, but it must
 * not be erasable, or the history of who could do what would disappear with it.
 * Deactivation revokes the privileges immediately without destroying the record.
 *
 * `code` is the stable machine key used by seeders, tests and future code.
 * `name` is the human label and may be reworded without touching authorization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The administration screens filter by state, and the authorization
            // query joins on it to ignore withdrawn roles.
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
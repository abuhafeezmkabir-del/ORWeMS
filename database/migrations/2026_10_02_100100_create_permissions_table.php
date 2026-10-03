<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3B — permissions.
 *
 * A permission is "an action on a resource", named `<resource>.<action>`:
 *
 *     beneficiaries.view
 *     beneficiaries.create
 *     beneficiaries.verify
 *     users.assign_roles
 *
 * The code is the ONLY authorization key. Names are display labels and may be
 * reworded freely; renaming a label must never change what an operator may do,
 * so no application logic may compare against `name`.
 *
 * Structured as its own table rather than a code string on the role, because
 * ORWeMS will grow domain-specific families (education, health, care) whose
 * visibility must differ from ordinary casework. That can only be expressed if
 * permissions are first-class rows that can be granted selectively.
 *
 * `is_active` follows the roles table: retire a permission by withdrawing it,
 * never by deleting it. A permission attached to a historical grant is
 * evidence of what that account was allowed to do at the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
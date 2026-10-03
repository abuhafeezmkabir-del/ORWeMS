<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3B — role-to-permission grant (many-to-many).
 *
 * Deletion policy: RESTRICT on both foreign keys, for the same reason as
 * role_user. Revoking a privilege is done by removing this row, which is
 * recorded; it is never done by destroying the permission or the role, which
 * would erase the record of what an account could once reach.
 *
 * No scope columns appear here. A grant answers "which capability", never
 * "which programme" or "which state" — that is Phase 3C, and it will hang off
 * these same rows rather than off the roles table, so nothing needs rewriting
 * when geographic scoping arrives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            // The same permission cannot be granted to the same role twice.
            $table->unique(['role_id', 'permission_id'], 'permission_role_role_permission_unique');

            // Serves Permission -> roles; the unique index serves role_id.
            $table->index('permission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
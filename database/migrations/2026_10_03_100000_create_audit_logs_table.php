<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3D — append-only audit log foundation.
 *
 * One row per security-relevant event. The table is deliberately narrow and
 * normalised:
 *
 *  - `user_id`     the operator who caused the event (nullable: a guest
 *                  resetting a password, or a seed run in a console, has no
 *                  authenticated actor).
 *  - `event`       a stable machine-readable name from
 *                  App\Enums\SecurityEvent. Stored as a value, not as a foreign
 *                  key: the vocabulary lives in version-controlled code, so it
 *                  can never be edited out of the audit trail from the
 *                  application side.
 *  - `subject_*`   polymorphic reference to the object that was changed. For
 *                  account-authorisation events this is always the affected
 *                  account, so `user_id` = who acted and `subject` = who was
 *                  acted upon.
 *  - `ip_address`, `user_agent`, `request_id`, `created_at`  request forensics.
 *  - `context`     small JSON bag for identifiers (role code, programme code,
 *                  area level). Sanitised before write: passwords, tokens,
 *                  secrets and hashes are dropped by App\Support\Audit\Auditor.
 *
 * Deliberately NOT stored: passwords (hashed or plain), session tokens,
 * remember tokens, CSRF tokens, reset tokens, request bodies, or any
 * beneficiary/child data.
 *
 * Delete protection is two-layered and intentional:
 *
 *  1. The Eloquent model refuses update() and delete() (App\Models\AuditLog),
 *     so ordinary application code cannot rewrite history.
 *  2. `user_id` uses RESTRICT, so an account with an audit trail cannot be
 *     removed out from under it. ORWeMS has no user-deletion path today; if one
 *     is ever added it must be designed around the audit record, not through it.
 *
 * There is no route, controller or console command that deletes audit rows.
 * Retention/archival is a deliberate future operation, performed offline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who performed the event. RESTRICT: an account that has acted in
            // ORWeMS is part of the record of what happened.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('event', 64);

            // Polymorphic subject. No foreign key is possible across five
            // candidate tables, so the reference is soft by necessity; it is
            // indexed so lookups stay cheap.
            $table->string('subject_type', 160)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // Correlation identifier for one HTTP request. UUIDs are 36 chars;
            // the column is sized to accept an externally supplied id as well.
            $table->string('request_id', 64)->nullable();

            $table->json('context')->nullable();

            // Append-only means there is no `updated_at`: a row is written once
            // and never revised.
            $table->timestamp('created_at')->useCurrent();

            $table->index('event');
            $table->index('created_at');
            $table->index('request_id');
            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

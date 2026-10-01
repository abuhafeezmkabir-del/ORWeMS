<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3A — user account foundation.
 *
 * The users table still carries Laravel's default authentication columns, which
 * remain correct for a session-based Blade application. This migration adds
 * only what ORWeMS can justify today:
 *
 *  - status                the account lifecycle, so access can be revoked;
 *  - password_changed_at   set on a successful reset, for account review;
 *  - last_login_at         when the account was last authenticated.
 *
 * Deliberately NOT added: a username column (email is the login identity),
 * a failed-login counter (Laravel's rate limiter covers this without storing
 * anything), an IP column (minimising personal data held about staff), and
 * role/permission columns (Phase 3B).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default(UserStatus::Active->value);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            // Account review and (Phase 3B) administration filter by status.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'password_changed_at', 'last_login_at']);
        });
    }
};

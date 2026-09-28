<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Natural persons (guardians, caregivers, parents, siblings, other
        // members). Beneficiaries live in a separate table; a link is made
        // via beneficiary_person_id on beneficiaries + person_relationships.
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('sex', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_guardian')->default(false);
            $table->boolean('is_caregiver')->default(false);
            $table->boolean('is_alive')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};

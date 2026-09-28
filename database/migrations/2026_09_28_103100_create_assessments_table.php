<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Longitudinal assessments: one beneficiary has many dated records.
        // History is never overwritten; assessor linkage is by name now and
        // by users FK later (Phase 3 leaves assessor_name informative only).
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained()->nullOnDelete();
            $table->date('assessment_date');
            $table->string('assessment_type', 40)->default('baseline');
            $table->string('assessor_name')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['beneficiary_id', 'assessment_date']);
            $table->index('assessment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};

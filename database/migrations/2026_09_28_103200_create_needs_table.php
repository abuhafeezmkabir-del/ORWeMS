<?php

use App\Enums\NeedStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Needs identified by an assessment. Priority is an ordinal
        // placeholder (no scoring algorithm in Phase 2; IWAE comes later).
        Schema::create('needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('need_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('priority')->nullable();
            $table->string('status', 20)->default(NeedStatus::Identified->value);
            $table->timestamps();

            $table->index(['assessment_id', 'need_category_id']);
            $table->index(['beneficiary_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('needs');
    }
};

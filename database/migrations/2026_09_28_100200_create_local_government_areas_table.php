<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Third-level division: LGA / district / equivalent.
        Schema::create('local_government_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('type_label')->default('LGA');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['state_id', 'name']);
            $table->index(['state_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_government_areas');
    }
};

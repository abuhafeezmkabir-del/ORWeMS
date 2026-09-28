<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_government_area_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('type_label')->default('Ward');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['local_government_area_id', 'name']);
            $table->index(['local_government_area_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wards');
    }
};

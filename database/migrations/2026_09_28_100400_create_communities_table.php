<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('local_government_area_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('type_label')->default('Community');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['local_government_area_id', 'name']);
            $table->index(['ward_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communities');
    }
};

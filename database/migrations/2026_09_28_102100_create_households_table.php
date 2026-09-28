<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('household_code', 30)->unique();
            $table->string('household_name')->nullable();
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('registration_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};

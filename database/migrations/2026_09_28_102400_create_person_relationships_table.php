<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relationship_type_id')->constrained()->restrictOnDelete();
            // Subject of the relationship (usually a person in the household).
            $table->foreignId('person_id')->constrained('persons')->restrictOnDelete();
            // Related party: either another person or a beneficiary child.
            $table->foreignId('related_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('related_beneficiary_id')->nullable()->constrained('beneficiaries')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_primary_guardian')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'relationship_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_relationships');
    }
};

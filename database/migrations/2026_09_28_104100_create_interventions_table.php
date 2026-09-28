<?php

use App\Enums\InterventionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Minimum intervention foundation. Links back to beneficiary and
        // optionally to the assessed need. Follow-ups/outcomes arrive later
        // as child tables of interventions without touching beneficiaries.
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('need_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('intervention_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default(InterventionStatus::Planned->value);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['beneficiary_id', 'status']);
            $table->index(['need_id', 'status']);
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};

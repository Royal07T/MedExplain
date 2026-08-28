<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('encounter_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->decimal('temperature', 5, 2)->nullable();
            $table->string('temperature_unit', 10)->default('C');
            $table->integer('heart_rate')->nullable();
            $table->integer('blood_pressure_systolic')->nullable();
            $table->integer('blood_pressure_diastolic')->nullable();
            $table->integer('respiratory_rate')->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->string('weight_unit', 10)->default('kg');
            $table->decimal('height', 6, 2)->nullable();
            $table->string('height_unit', 10)->default('cm');
            $table->decimal('bmi', 5, 2)->nullable();
            $table->integer('pain_score')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
            $table->index('encounter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
    }
};

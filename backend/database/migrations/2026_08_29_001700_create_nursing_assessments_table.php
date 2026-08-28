<?php

use App\Enums\AssessmentType;
use App\Enums\FallRiskLevel;
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
        Schema::create('nursing_assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('patient_id');
            $table->string('assessment_type', 30)->default(AssessmentType::Shift->value);
            $table->string('template_name', 255)->nullable();
            $table->json('assessment_data')->nullable();
            $table->text('findings')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('assessment_time')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->unsignedTinyInteger('fall_risk_score')->nullable();
            $table->string('fall_risk_level', 20)->nullable();
            $table->string('pressure_ulcer_stage', 30)->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'patient_id', 'assessment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nursing_assessments');
    }
};

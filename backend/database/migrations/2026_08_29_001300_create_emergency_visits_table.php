<?php

use App\Enums\AcuityLevel;
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
        Schema::create('emergency_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('clinician_id')->nullable();
            $table->unsignedBigInteger('triage_nurse_id')->nullable();
            $table->string('chief_complaint', 500)->nullable();
            $table->string('acuity_level', 20)->default(AcuityLevel::Nonurgent->value);
            $table->string('queue_status', 20)->default('waiting');
            $table->string('disposition', 30)->nullable();
            $table->timestamp('arrival_time')->nullable();
            $table->timestamp('seen_by_clinician_at')->nullable();
            $table->timestamp('departure_time')->nullable();
            $table->text('vitals_summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('clinician_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('triage_nurse_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['organization_id', 'queue_status']);
            $table->index('acuity_level');
            $table->index('arrival_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_visits');
    }
};

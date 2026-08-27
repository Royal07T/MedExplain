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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('clinician_id');
            $table->enum('status', ['scheduled', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->enum('acuity_level', ['resuscitation', 'emergent', 'urgent', 'non-urgent'])->default('non-urgent');
            $table->string('chief_complaint')->nullable();
            $table->text('symptoms')->nullable();
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('clinician_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index('patient_id');
            $table->index('organization_id');
            $table->index('status');
            $table->index('acuity_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
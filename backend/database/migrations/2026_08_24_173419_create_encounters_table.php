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
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('clinician_id')->nullable();
            $table->unsignedBigInteger('triage_id')->nullable();
            $table->string('chief_complaint')->nullable();
            $table->text('symptoms')->nullable();
            $table->text('clinical_observations')->nullable();
            $table->enum('acuity_level', ['resuscitation', 'emergent', 'urgent', 'non-urgent'])->default('non-urgent');
            $table->string('queue_status')->default('waiting');
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->decimal('vitals_summary', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'organization_id']);
            $table->index('clinician_id');
            $table->index('triage_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};

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
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('prescription_id')->nullable();
            $table->string('medication_name', 255);
            $table->string('dose', 100)->nullable();
            $table->string('dose_unit', 50)->nullable();
            $table->string('route', 50)->nullable();
            $table->timestamp('scheduled_time')->nullable();
            $table->timestamp('administered_time')->nullable();
            $table->string('status', 20)->default('not_given');
            $table->unsignedBigInteger('administered_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('vitals_before', 255)->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->nullOnDelete();
            $table->foreign('administered_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'patient_id', 'scheduled_time'], 'med_admin_org_patient_time_idx');
            $table->index(['organization_id', 'status'], 'med_admin_org_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
    }
};

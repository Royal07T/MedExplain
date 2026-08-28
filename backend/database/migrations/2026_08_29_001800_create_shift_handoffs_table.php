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
        Schema::create('shift_handoffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('from_nurse_id')->nullable();
            $table->unsignedBigInteger('to_nurse_id')->nullable();
            $table->string('unit', 50)->nullable();
            $table->timestamp('shift_start')->nullable();
            $table->timestamp('shift_end')->nullable();
            $table->text('clinical_summary')->nullable();
            $table->text('tasks_to_complete')->nullable();
            $table->text('medication_review')->nullable();
            $table->text('safety_concerns')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->timestamp('handoff_time')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('from_nurse_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('to_nurse_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'patient_id']);
            $table->index(['organization_id', 'handoff_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_handoffs');
    }
};

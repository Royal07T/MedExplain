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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('patient');
        });

        Schema::create('clinician_patient_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinician_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['clinician_user_id', 'patient_user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinician_patient_access');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
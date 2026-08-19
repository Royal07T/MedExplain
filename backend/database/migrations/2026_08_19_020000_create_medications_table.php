<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Medication extraction & history (roadmap #6 — Medication Intelligence).
     */
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('strength', 100)->nullable();
            $table->string('dosage_form', 100)->nullable();
            $table->string('dose', 100)->nullable();
            $table->string('frequency', 100)->nullable();
            $table->string('route', 100)->nullable();
            $table->string('prescriber', 255)->nullable();
            $table->string('indications', 500)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
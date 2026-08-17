<?php

use App\Enums\LabResultStatus;
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
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_extraction_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('value', 255);
            $table->string('unit', 100)->nullable();
            $table->string('reference_range', 255)->nullable();
            $table->enum('status', array_column(LabResultStatus::cases(), 'value'))->default(LabResultStatus::Unknown->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('document_extraction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_results');
    }
};
<?php

use App\Enums\AiAnalysisStatus;
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
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_document_id')->constrained()->cascadeOnDelete();
            $table->enum('status', array_column(AiAnalysisStatus::cases(), 'value'))->default(AiAnalysisStatus::Pending->value);
            $table->longText('summary')->nullable();
            $table->longText('disclaimer')->nullable();
            $table->json('concerns')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index('medical_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
<?php

use App\Enums\AnalysisItemCategory;
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
        Schema::create('analysis_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_analysis_id')->constrained()->cascadeOnDelete();
            $table->string('test_name', 255);
            $table->longText('explanation');
            $table->enum('category', array_column(AnalysisItemCategory::cases(), 'value'));
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('ai_analysis_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_items');
    }
};
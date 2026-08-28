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
        Schema::create('lab_test_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('test_code')->unique();
            $table->string('test_name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('specimen_type')->default('blood');
            $table->string('container_type')->nullable();
            $table->integer('turnaround_hours')->default(24);
            $table->decimal('cost', 10, 2)->nullable();
            
            // Reference ranges
            $table->json('reference_ranges')->nullable();
            
            // Critical values
            $table->json('critical_values')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'test_code']);
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_catalogs');
    }
};

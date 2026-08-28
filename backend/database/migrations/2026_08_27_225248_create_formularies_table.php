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
        Schema::create('formularies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('medication_id')->constrained()->onDelete('cascade');
            $table->string('formulary_code')->nullable();
            $table->enum('tier', ['generic', 'preferred_brand', 'non_preferred', 'specialty'])->default('generic');
            $table->boolean('requires_prior_authorization')->default(false);
            $table->integer('quantity_limit')->nullable();
            $table->integer('days_supply_limit')->nullable();
            $table->text('restrictions')->nullable();
            $table->text('alternatives')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_date')->nullable();
            $table->date('discontinued_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'medication_id']);
            $table->index('tier');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formularies');
    }
};

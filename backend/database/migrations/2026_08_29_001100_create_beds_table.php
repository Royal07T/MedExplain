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
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('ward_id');
            $table->string('bed_number', 50);
            $table->string('bed_type', 50)->default('standard');
            $table->boolean('is_occupied')->default(false);
            $table->string('cleaning_status', 20)->default('clean');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('ward_id')->references('id')->on('wards')->cascadeOnDelete();

            $table->index('organization_id');
            $table->index('ward_id');
            $table->index('is_occupied');
            $table->index('cleaning_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beds');
    }
};

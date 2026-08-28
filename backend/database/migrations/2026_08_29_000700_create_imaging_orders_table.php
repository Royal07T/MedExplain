<?php

use App\Enums\ImagingOrderStatus;
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
        Schema::create('imaging_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('clinician_id');
            $table->string('modality', 50);
            $table->string('body_region', 100)->nullable();
            $table->text('clinical_indication')->nullable();
            $table->string('priority', 20)->default('routine');
            $table->string('status', 50)->default(ImagingOrderStatus::Pending->value);
            $table->string('icd_code', 20)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->decimal('radiation_dose_mgy', 8, 2)->nullable();
            $table->unsignedInteger('image_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('clinician_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index('user_id');
            $table->index('organization_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imaging_orders');
    }
};

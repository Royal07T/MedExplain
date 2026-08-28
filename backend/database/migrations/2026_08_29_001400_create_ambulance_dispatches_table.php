<?php

use App\Enums\AmbulanceDispatchStatus;
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
        Schema::create('ambulance_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('emergency_visit_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('status', 20)->default(AmbulanceDispatchStatus::Dispatched->value);
            $table->string('pickup_location', 255)->nullable();
            $table->string('destination_hospital', 255)->nullable();
            $table->string('vehicle_id', 50)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('on_scene_at')->nullable();
            $table->timestamp('transporting_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('emergency_visit_id')->references('id')->on('emergency_visits')->nullOnDelete();
            $table->foreign('patient_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index('dispatched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ambulance_dispatches');
    }
};

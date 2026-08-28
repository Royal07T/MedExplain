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
        Schema::create('radiology_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imaging_order_id');
            $table->unsignedBigInteger('radiologist_id');
            $table->text('findings')->nullable();
            $table->text('impression')->nullable();
            $table->longText('report_text')->nullable();
            $table->string('status', 50)->default('draft');
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->foreign('imaging_order_id')->references('id')->on('imaging_orders')->cascadeOnDelete();
            $table->foreign('radiologist_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radiology_reports');
    }
};

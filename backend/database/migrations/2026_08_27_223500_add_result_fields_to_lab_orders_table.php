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
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->string('result_value')->nullable();
            $table->string('result_unit')->nullable();
            $table->decimal('reference_range_low', 10, 2)->nullable();
            $table->decimal('reference_range_high', 10, 2)->nullable();
            $table->boolean('is_abnormal')->default(false);
            $table->text('explanation')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropColumn(['result_value', 'result_unit', 'reference_range_low', 'reference_range_high', 'is_abnormal', 'explanation']);
        });
    }
};

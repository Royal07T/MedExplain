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
        Schema::create('drug_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('medication_id')->constrained()->onDelete('cascade');
            $table->string('batch_number')->nullable();
            $table->date('expiry_date');
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('minimum_stock_level')->default(10);
            $table->integer('maximum_stock_level')->default(1000);
            $table->string('location')->nullable();
            $table->string('supplier')->nullable();
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->enum('status', ['available', 'reserved', 'expired', 'recalled'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'medication_id']);
            $table->index('expiry_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drug_inventories');
    }
};

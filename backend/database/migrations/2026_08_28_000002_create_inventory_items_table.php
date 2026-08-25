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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('sku', 50)->unique();
            $table->string('item_type', 50)->default('medication'); // medication, consumable, equipment
            $table->enum('status', ['in_stock', 'low_stock', 'out_of_stock', 'maintenance'])->default('in_stock');
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('minimum_stock_level')->default(10);
            $table->integer('maximum_stock_level')->default(1000);
            $table->string('batch_number')->nullable();
            $table->date('expiration_date')->nullable();
            $table->text('supplier')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('item_type');
            $table->index('status');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
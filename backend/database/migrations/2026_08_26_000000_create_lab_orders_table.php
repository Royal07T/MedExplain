<?php

use App\Enums\LabOrderStatus;
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
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinician_id')->constrained('users')->nullOnDelete();
            $table->string('test_name', 255);
            $table->string('test_code', 50)->nullable(); // LOINC or internal code
            $table->string('status', 50)->default(LabOrderStatus::Pending->value);
            $table->date('result_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('result_received_at')->nullable();
            $table->timestamps();

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
        Schema::dropIfExists('lab_orders');
    }
};
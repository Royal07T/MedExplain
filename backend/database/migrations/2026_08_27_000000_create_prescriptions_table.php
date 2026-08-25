<?php

use App\Enums\MedicationStatus;
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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinician_id')->constrained('users')->nullOnDelete();
            $table->foreignId('medication_id')->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->enum('status', array_column(MedicationStatus::cases(), 'value'))
                ->default(MedicationStatus::Prescribed->value);
            $table->date('expires_at')->nullable();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamps();

            $table->index('user_id');
            $table->index('organization_id');
            $table->index('status');
            $table->index('medication_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
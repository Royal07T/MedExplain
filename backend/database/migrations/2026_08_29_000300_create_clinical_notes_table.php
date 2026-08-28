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
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('encounter_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->nullable();
            $table->enum('note_type', ['admission', 'progress', 'discharge', 'consultation', 'procedure', 'other']);
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->text('full_note')->nullable();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cosigner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('cosigned_at')->nullable();
            $table->enum('status', ['draft', 'final', 'amended'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'created_at']);
            $table->index('encounter_id');
            $table->index('note_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_notes');
    }
};

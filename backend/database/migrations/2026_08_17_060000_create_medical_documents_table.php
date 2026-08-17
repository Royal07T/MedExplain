<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
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
        Schema::create('medical_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->string('storage_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->enum('document_type', array_column(DocumentType::cases(), 'value'))->default(DocumentType::Unknown->value);
            $table->enum('status', array_column(DocumentStatus::cases(), 'value'))->default(DocumentStatus::Uploaded->value);
            $table->string('error_message', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_documents');
    }
};
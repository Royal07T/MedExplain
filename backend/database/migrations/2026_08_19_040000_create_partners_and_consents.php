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
        Schema::create('api_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_id')->unique();
            $table->string('client_secret');
            $table->json('scopes')->nullable();
            $table->unsignedInteger('quota_per_minute')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('api_partners')->cascadeOnDelete();
            $table->foreignId('patient_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('scopes')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unique(['partner_id', 'patient_user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('api_partners');
    }
};
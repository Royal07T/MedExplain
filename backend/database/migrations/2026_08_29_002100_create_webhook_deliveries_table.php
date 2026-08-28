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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_subscription_id');
            $table->string('event', 100);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('webhook_subscription_id')->references('id')->on('webhook_subscriptions')->cascadeOnDelete();

            $table->index('webhook_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};

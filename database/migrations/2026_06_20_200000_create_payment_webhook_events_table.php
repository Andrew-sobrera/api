<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('asaas');
            $table->string('payment_id');
            $table->string('event_name')->nullable();
            $table->string('mapped_status')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('processing_result');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'payment_id', 'event_name'], 'payment_webhook_events_idempotency');
            $table->index(['payment_id', 'processing_result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};

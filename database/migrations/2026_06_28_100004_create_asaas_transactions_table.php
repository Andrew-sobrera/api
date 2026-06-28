<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asaas_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('asaas_payment_id')->nullable()->index();
            $table->string('asaas_customer_id')->nullable();
            $table->foreignId('producer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                      // PAYMENT, REFUND, CHARGEBACK, SUBCONTA
            $table->string('status')->default('PENDING');
            $table->unsignedInteger('amount')->default(0); // centavos
            $table->unsignedInteger('fee_amount')->default(0);
            $table->unsignedInteger('net_amount')->default(0);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asaas_transactions');
    }
};

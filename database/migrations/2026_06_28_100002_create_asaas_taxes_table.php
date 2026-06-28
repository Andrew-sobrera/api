<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asaas_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('payment_type');          // PIX, CREDIT_CARD, BOLETO, DEBIT_CARD
            $table->unsignedTinyInteger('installment_min')->default(1);
            $table->unsignedTinyInteger('installment_max')->default(1);
            $table->unsignedInteger('fixed_fee')->default(0);   // centavos
            $table->decimal('percentage_fee', 5, 4)->default(0); // ex: 0.0299 = 2.99%
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['payment_type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asaas_taxes');
    }
};

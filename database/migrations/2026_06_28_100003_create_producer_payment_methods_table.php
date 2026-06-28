<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producer_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method'); // PIX, CREDIT_CARD, BOLETO, DEBIT_CARD
            $table->unsignedTinyInteger('max_installments')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['producer_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producer_payment_methods');
    }
};

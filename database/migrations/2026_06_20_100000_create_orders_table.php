<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('event_id')->constrained('events');
            $table->string('status');
            $table->unsignedBigInteger('total_amount');
            $table->string('asaas_payment_id')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('asaas_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

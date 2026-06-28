<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('ticket_amount')->default(0)->after('total_amount');
            $table->unsignedInteger('gateway_fee')->default(0)->after('ticket_amount');
            $table->unsignedInteger('platform_commission')->default(0)->after('gateway_fee');
            $table->unsignedInteger('producer_amount')->default(0)->after('platform_commission');
            $table->unsignedTinyInteger('installments')->default(1)->after('producer_amount');
            $table->string('payment_fee_mode')->nullable()->after('installments');
            $table->string('chargeback_status')->nullable()->after('payment_fee_mode');
            $table->timestamp('refunded_at')->nullable()->after('chargeback_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ticket_amount',
                'gateway_fee',
                'platform_commission',
                'producer_amount',
                'installments',
                'payment_fee_mode',
                'chargeback_status',
                'refunded_at',
            ]);
        });
    }
};

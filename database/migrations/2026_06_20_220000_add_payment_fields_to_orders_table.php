<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('total_amount');
            $table->string('payment_status')->default('PENDING')->after('payment_method');
            $table->text('pix_payload')->nullable()->after('asaas_payment_id');
            $table->text('pix_qr_code_url')->nullable()->after('pix_payload');
            $table->json('payment_response')->nullable()->after('pix_qr_code_url');

            $table->index('payment_status');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['payment_method']);
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'pix_payload',
                'pix_qr_code_url',
                'payment_response',
            ]);
        });
    }
};

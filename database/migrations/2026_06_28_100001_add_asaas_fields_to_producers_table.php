<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->string('fantasy_name')->nullable()->after('name');
            $table->string('phone')->nullable()->after('cnpj');
            $table->string('email')->nullable()->after('phone');
            $table->json('address')->nullable()->after('email');

            // Asaas subconta
            $table->string('asaas_account_id')->nullable()->unique()->after('address');
            $table->string('asaas_wallet_id')->nullable()->after('asaas_account_id');
            $table->string('asaas_status')->default('PENDING')->after('asaas_wallet_id');
            $table->boolean('asaas_onboarding_completed')->default(false)->after('asaas_status');
            $table->timestamp('asaas_created_at')->nullable()->after('asaas_onboarding_completed');

            // Configurações financeiras
            $table->decimal('ticket_commission_percentage', 5, 2)->default(5.00)->after('asaas_created_at');
            $table->string('payment_fee_mode')->default('CUSTOMER')->after('ticket_commission_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->dropColumn([
                'fantasy_name',
                'phone',
                'email',
                'address',
                'asaas_account_id',
                'asaas_wallet_id',
                'asaas_status',
                'asaas_onboarding_completed',
                'asaas_created_at',
                'ticket_commission_percentage',
                'payment_fee_mode',
            ]);
        });
    }
};

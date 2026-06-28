<?php

namespace Database\Seeders;

use App\Models\AsaasTax;
use Illuminate\Database\Seeder;

/**
 * Tabela de taxas do Asaas.
 * Valores em centavos para fixed_fee e como decimal (ex: 0.0299 = 2.99%) para percentage_fee.
 *
 * Referência: https://asaas.com/taxas
 */
class AsaasTaxSeeder extends Seeder
{
    public function run(): void
    {
        AsaasTax::query()->delete();

        $taxes = [
            // ─── PIX ──────────────────────────────────────────────────────────
            [
                'payment_type' => 'PIX',
                'installment_min' => 1,
                'installment_max' => 1,
                'fixed_fee' => 99,        // R$ 0,99 (taxa promocional)
                'percentage_fee' => 0.0000,
                'active' => true,
            ],
            // Fallback padrão quando não há promoção
            [
                'payment_type' => 'PIX',
                'installment_min' => 1,
                'installment_max' => 1,
                'fixed_fee' => 199,       // R$ 1,99
                'percentage_fee' => 0.0000,
                'active' => false,
            ],

            // ─── BOLETO ───────────────────────────────────────────────────────
            [
                'payment_type' => 'BOLETO',
                'installment_min' => 1,
                'installment_max' => 1,
                'fixed_fee' => 99,        // R$ 0,99 (taxa promocional)
                'percentage_fee' => 0.0000,
                'active' => true,
            ],
            [
                'payment_type' => 'BOLETO',
                'installment_min' => 1,
                'installment_max' => 1,
                'fixed_fee' => 199,       // R$ 1,99
                'percentage_fee' => 0.0000,
                'active' => false,
            ],

            // ─── DÉBITO ───────────────────────────────────────────────────────
            [
                'payment_type' => 'DEBIT_CARD',
                'installment_min' => 1,
                'installment_max' => 1,
                'fixed_fee' => 35,        // R$ 0,35
                'percentage_fee' => 0.0189, // 1.89%
                'active' => true,
            ],

            // ─── CRÉDITO 1x ───────────────────────────────────────────────────
            [
                'payment_type' => 'CREDIT_CARD',
                'installment_min' => 1,
                'installment_max' => 1,
                'fixed_fee' => 49,        // R$ 0,49
                'percentage_fee' => 0.0299, // 2.99%
                'active' => true,
            ],

            // ─── CRÉDITO 2-6x ─────────────────────────────────────────────────
            [
                'payment_type' => 'CREDIT_CARD',
                'installment_min' => 2,
                'installment_max' => 6,
                'fixed_fee' => 49,        // R$ 0,49
                'percentage_fee' => 0.0349, // 3.49%
                'active' => true,
            ],

            // ─── CRÉDITO 7-12x ────────────────────────────────────────────────
            [
                'payment_type' => 'CREDIT_CARD',
                'installment_min' => 7,
                'installment_max' => 12,
                'fixed_fee' => 49,        // R$ 0,49
                'percentage_fee' => 0.0399, // 3.99%
                'active' => true,
            ],
        ];

        foreach ($taxes as $tax) {
            AsaasTax::create($tax);
        }

        $this->command->info('AsaasTaxSeeder: '.count($taxes).' registros inseridos.');
    }
}

<?php

namespace App\Services\Payments;

use App\DTOs\CheckoutFeeBreakdown;
use App\Enums\PaymentFeeMode;
use App\Models\AsaasTax;
use App\Models\Producer;
use Illuminate\Support\Facades\Cache;

/**
 * Calcula as taxas do Asaas e o breakdown financeiro de um checkout.
 *
 * Regras:
 *  - Taxas são buscadas do banco (tabela asaas_taxes), nunca hardcoded
 *  - payment_fee_mode = CUSTOMER: comprador paga taxa → total = ticket + fee
 *  - payment_fee_mode = PRODUCER: produtor absorve taxa → total = ticket
 *  - Comissão da plataforma é sempre sobre o valor bruto dos ingressos
 */
class AsaasFeeCalculatorService
{
    private const CACHE_TTL = 3600; // 1 hora

    /**
     * Calcula o breakdown completo para um checkout.
     *
     * @param  int     $ticketAmountCents  Valor total dos ingressos em centavos
     * @param  string  $paymentType        PIX, CREDIT_CARD, BOLETO, DEBIT_CARD
     * @param  float   $commissionPct      Comissão da plataforma em % (ex: 5.0)
     * @param  string  $feeMode            CUSTOMER ou PRODUCER
     * @param  int     $installments       Número de parcelas (1-12)
     */
    public function calculate(
        int $ticketAmountCents,
        string $paymentType,
        float $commissionPct,
        string $feeMode,
        int $installments = 1,
    ): CheckoutFeeBreakdown {
        $tax = $this->findTax($paymentType, $installments);
        $gatewayFee = $this->computeGatewayFee($ticketAmountCents, $tax);
        $platformCommission = $this->computeCommission($ticketAmountCents, $commissionPct);

        if ($feeMode === PaymentFeeMode::CUSTOMER->value) {
            $totalCustomerAmount = $ticketAmountCents + $gatewayFee;
            $producerAmount = $ticketAmountCents - $platformCommission;
        } else {
            // PRODUCER absorve as taxas
            $totalCustomerAmount = $ticketAmountCents;
            $producerAmount = $ticketAmountCents - $platformCommission - $gatewayFee;
        }

        return new CheckoutFeeBreakdown(
            ticketAmount: $ticketAmountCents,
            gatewayFee: $gatewayFee,
            platformCommission: $platformCommission,
            producerAmount: max(0, $producerAmount),
            totalCustomerAmount: $totalCustomerAmount,
            paymentFeeMode: $feeMode,
            installments: $installments,
        );
    }

    /**
     * Atalho para calcular com as configurações do produtor.
     */
    public function calculateForProducer(
        int $ticketAmountCents,
        string $paymentType,
        Producer $producer,
        int $installments = 1,
    ): CheckoutFeeBreakdown {
        return $this->calculate(
            ticketAmountCents: $ticketAmountCents,
            paymentType: $paymentType,
            commissionPct: (float) $producer->ticket_commission_percentage,
            feeMode: $producer->payment_fee_mode?->value ?? PaymentFeeMode::CUSTOMER->value,
            installments: $installments,
        );
    }

    /**
     * Retorna somente o valor da taxa do gateway para um cenário.
     */
    public function getGatewayFee(string $paymentType, int $amountCents, int $installments = 1): int
    {
        $tax = $this->findTax($paymentType, $installments);

        return $this->computeGatewayFee($amountCents, $tax);
    }

    private function findTax(string $paymentType, int $installments): AsaasTax
    {
        $cacheKey = "asaas_tax_{$paymentType}_{$installments}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($paymentType, $installments) {
            $tax = AsaasTax::query()
                ->where('payment_type', strtoupper($paymentType))
                ->where('active', true)
                ->where('installment_min', '<=', $installments)
                ->where('installment_max', '>=', $installments)
                ->where(function ($q) {
                    $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()->toDateString());
                })
                ->where(function ($q) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString());
                })
                ->orderByDesc('id') // registro mais recente ganha
                ->first();

            if (! $tax) {
                // Fallback sem taxas caso não exista registro para o tipo
                $tax = new AsaasTax([
                    'payment_type' => $paymentType,
                    'installment_min' => 1,
                    'installment_max' => 12,
                    'fixed_fee' => 0,
                    'percentage_fee' => 0,
                ]);
            }

            return $tax;
        });
    }

    private function computeGatewayFee(int $amountCents, AsaasTax $tax): int
    {
        $percentage = (float) $tax->percentage_fee;
        $fixed = (int) $tax->fixed_fee;

        return $fixed + (int) round($amountCents * $percentage);
    }

    private function computeCommission(int $amountCents, float $commissionPct): int
    {
        return (int) round($amountCents * ($commissionPct / 100));
    }
}

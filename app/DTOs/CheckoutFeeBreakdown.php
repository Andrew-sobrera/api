<?php

namespace App\DTOs;

/**
 * Breakdown financeiro de um checkout.
 * Todos os valores são em centavos.
 */
readonly class CheckoutFeeBreakdown
{
    public function __construct(
        public int $ticketAmount,
        public int $gatewayFee,
        public int $platformCommission,
        public int $producerAmount,
        public int $totalCustomerAmount,
        public string $paymentFeeMode,
        public int $installments = 1,
    ) {
    }

    public function toArray(): array
    {
        return [
            'ticket_amount' => $this->ticketAmount,
            'gateway_fee' => $this->gatewayFee,
            'platform_commission' => $this->platformCommission,
            'producer_amount' => $this->producerAmount,
            'total_customer_amount' => $this->totalCustomerAmount,
            'payment_fee_mode' => $this->paymentFeeMode,
            'installments' => $this->installments,
        ];
    }

    public function toFormattedArray(): array
    {
        return [
            'ticket_amount' => $this->formatCents($this->ticketAmount),
            'gateway_fee' => $this->formatCents($this->gatewayFee),
            'platform_commission' => $this->formatCents($this->platformCommission),
            'producer_amount' => $this->formatCents($this->producerAmount),
            'total_customer_amount' => $this->formatCents($this->totalCustomerAmount),
            'payment_fee_mode' => $this->paymentFeeMode,
            'installments' => $this->installments,
        ];
    }

    private function formatCents(int $cents): float
    {
        return round($cents / 100, 2);
    }
}

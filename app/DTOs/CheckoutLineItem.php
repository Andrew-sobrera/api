<?php

namespace App\DTOs;

readonly class CheckoutLineItem
{
    public function __construct(
        public int $eventTicketId,
        public int $quantity,
        public ?int $batchId = null,
        public ?int $seatId = null,
        public ?int $sectorId = null,
        public int $unitPrice = 0,
    ) {
    }
}

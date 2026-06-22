<?php

namespace App\Services;

use App\DTOs\CheckoutLineItem;
use App\Enums\TicketEventStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\TicketBatch;
use App\Models\TicketEvent;
use App\Repositories\EventTicketRepository;

class CheckoutItemResolver
{
    public function __construct(
        protected EventTicketRepository $eventTicketRepository,
        protected TicketBatchService $batchService,
        protected SeatService $seatService,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return CheckoutLineItem[]
     */
    public function resolve(array $rawItems): array
    {
        $resolved = [];

        foreach ($rawItems as $raw) {
            $resolved[] = $this->resolveOne($raw);
        }

        return $resolved;
    }

    private function resolveOne(array $raw): CheckoutLineItem
    {
        $ticket = $this->eventTicketRepository->findById((int) $raw['event_ticket_id']);

        if ($ticket->status !== TicketEventStatus::ACTIVE) {
            throw new InsufficientStockException('Ingresso indisponível.');
        }

        $quantity = (int) ($raw['quantity'] ?? 1);
        $seatId = isset($raw['seat_id']) ? (int) $raw['seat_id'] : null;
        $batchId = isset($raw['batch_id']) ? (int) $raw['batch_id'] : null;
        $sectorId = $ticket->sector_id ?? (isset($raw['sector_id']) ? (int) $raw['sector_id'] : null);

        if ($seatId) {
            $quantity = 1;
        }

        $batch = null;
        $unitPrice = $ticket->price;

        if ($batchId) {
            $batch = TicketBatch::findOrFail($batchId);

            if (! $this->batchService->isBatchAvailable($batch)) {
                throw new InsufficientStockException('Lote indisponível.');
            }

            $unitPrice = $batch->price;
        } elseif ($ticket->batches()->exists()) {
            $batch = $this->batchService->getActiveBatch($ticket, $sectorId);

            if (! $batch) {
                throw new InsufficientStockException('Nenhum lote ativo disponível.');
            }

            $batchId = $batch->id;
            $unitPrice = $batch->price;
        }

        if ($seatId) {
            $seat = $this->seatService->getAvailableSeatsForSector($sectorId ?? $ticket->sector_id)
                ->firstWhere('id', $seatId);

            if (! $seat) {
                throw new InsufficientStockException('Assento indisponível.');
            }
        }

        return new CheckoutLineItem(
            eventTicketId: $ticket->id,
            quantity: $quantity,
            batchId: $batchId,
            seatId: $seatId,
            sectorId: $sectorId,
            unitPrice: $unitPrice,
        );
    }
}

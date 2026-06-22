<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\Event;
use App\Models\EventSector;
use App\Models\Seat;
use App\Models\TicketBatch;
use App\Models\TicketEvent;
use Illuminate\Support\Collection;

class TicketBatchService
{
    public function getActiveBatch(TicketEvent $ticket, ?int $sectorId = null): ?TicketBatch
    {
        $query = TicketBatch::query()
            ->where('ticket_event_id', $ticket->id)
            ->where('status', BatchStatus::ACTIVE)
            ->orderBy('sort_order');

        if ($sectorId) {
            $query->where('sector_id', $sectorId);
        }

        $batch = $query->first();

        if ($batch && $this->isBatchAvailable($batch)) {
            return $batch;
        }

        return $this->resolveNextAvailableBatch($ticket, $sectorId);
    }

    public function resolveNextAvailableBatch(TicketEvent $ticket, ?int $sectorId = null): ?TicketBatch
    {
        $query = TicketBatch::query()
            ->where('ticket_event_id', $ticket->id)
            ->whereIn('status', [BatchStatus::PENDING, BatchStatus::ACTIVE])
            ->orderBy('sort_order');

        if ($sectorId) {
            $query->where('sector_id', $sectorId);
        }

        foreach ($query->get() as $batch) {
            if ($this->isBatchAvailable($batch)) {
                if ($batch->status !== BatchStatus::ACTIVE) {
                    $batch->update(['status' => BatchStatus::ACTIVE]);
                }

                return $batch->fresh();
            }

            if ($batch->availableQuantity() <= 0) {
                $batch->update(['status' => BatchStatus::SOLD_OUT]);
            } elseif ($batch->ends_at && $batch->ends_at->isPast()) {
                $batch->update(['status' => BatchStatus::EXPIRED]);
            }
        }

        return null;
    }

    public function isBatchAvailable(TicketBatch $batch): bool
    {
        if ($batch->status === BatchStatus::SOLD_OUT || $batch->status === BatchStatus::EXPIRED) {
            return false;
        }

        if ($batch->starts_at && $batch->starts_at->isFuture()) {
            return false;
        }

        if ($batch->ends_at && $batch->ends_at->isPast()) {
            return false;
        }

        return $batch->availableQuantity() > 0;
    }

    public function decrementBatchStock(TicketBatch $batch, int $quantity): TicketBatch
    {
        $batch->increment('sold_quantity', $quantity);

        if ($batch->fresh()->availableQuantity() <= 0) {
            $batch->update(['status' => BatchStatus::SOLD_OUT]);
            $this->resolveNextAvailableBatch($batch->ticketEvent, $batch->sector_id);
        }

        return $batch->fresh();
    }

    public function incrementBatchStock(TicketBatch $batch, int $quantity): TicketBatch
    {
        $batch->decrement('sold_quantity', $quantity);

        if ($batch->status === BatchStatus::SOLD_OUT && $batch->availableQuantity() > 0) {
            $batch->update(['status' => BatchStatus::ACTIVE]);
        }

        return $batch->fresh();
    }

    public function getPublicBatchesForEvent(Event $event): Collection
    {
        return TicketBatch::query()
            ->whereHas('ticketEvent', fn ($q) => $q->where('event_id', $event->id))
            ->where('status', BatchStatus::ACTIVE)
            ->with(['ticketEvent', 'sector'])
            ->orderBy('sort_order')
            ->get();
    }
}

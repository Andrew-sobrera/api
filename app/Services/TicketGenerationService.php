<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Repositories\TicketRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class TicketGenerationService
{
    public function __construct(
        protected TicketRepository $ticketRepository,
        protected TicketHashService $hashService,
        protected CloudinaryService $cloudinaryService,
        protected SeatService $seatService,
    ) {
    }

    public function generateForOrder(Order $order): void
    {
        $order->load(['items.eventTicket', 'items.seat', 'items.batch', 'items.sector', 'user', 'event']);

        $this->completeMissingQrCodes($order);

        foreach ($order->items as $item) {
            $this->generateMissingTicketsForItem($order, $item);
        }

        if (! $this->isOrderFullyGenerated($order)) {
            throw new RuntimeException("Ticket generation incomplete for order {$order->id}");
        }
    }

    public function isOrderFullyGenerated(Order $order): bool
    {
        if (! $order->relationLoaded('items')) {
            $order->load('items');
        }

        $expected = $this->expectedTicketCount($order);
        $tickets = $this->ticketRepository->getForOrder($order->id);

        if ($tickets->count() < $expected) {
            return false;
        }

        return $tickets->every(fn (Ticket $ticket) => filled($ticket->qr_code_url));
    }

    private function completeMissingQrCodes(Order $order): void
    {
        $tickets = $this->ticketRepository->getForOrder($order->id);

        foreach ($tickets as $ticket) {
            if (! $ticket->qr_code_url) {
                $this->attachQrCode($ticket);
            }
        }
    }

    private function generateMissingTicketsForItem(Order $order, OrderItem $item): void
    {
        $units = $item->seat_id ? 1 : $item->quantity;
        $existing = $this->ticketRepository
            ->countForOrderLine($order->id, $item->event_ticket_id, $item->seat_id);

        $missing = $units - $existing;

        for ($i = 0; $i < $missing; $i++) {
            $this->createTicketForItem($order, $item);
        }
    }

    private function createTicketForItem(Order $order, OrderItem $item): Ticket
    {
        $ticket = DB::transaction(function () use ($order, $item) {
            $ticket = $this->ticketRepository->create([
                'order_id' => $order->id,
                'event_id' => $order->event_id,
                'event_ticket_id' => $item->event_ticket_id,
                'sector_id' => $item->sector_id,
                'seat_id' => $item->seat_id,
                'batch_id' => $item->batch_id,
                'buyer_name' => $order->user->name,
                'buyer_email' => $order->user->email,
                'status' => TicketStatus::GENERATED,
                'hash' => (string) Str::uuid(),
            ]);

            $hash = $this->hashService->generate($order->event, $order->user->email, $ticket->id);

            return $this->ticketRepository->update($ticket, ['hash' => $hash]);
        });

        Log::info('Ticket created for order', [
            'order_id' => $order->id,
            'ticket_id' => $ticket->id,
            'buyer_email' => $ticket->buyer_email,
        ]);

        $this->attachQrCode($ticket);

        if ($item->seat_id) {
            $this->seatService->confirmSeat($item->seat_id);
        }

        return $ticket->fresh();
    }

    private function attachQrCode(Ticket $ticket): Ticket
    {
        if ($ticket->qr_code_url) {
            return $ticket;
        }

        if (! $ticket->hash) {
            throw new RuntimeException("Ticket {$ticket->id} has no hash for QR generation.");
        }

        try {
            $upload = $this->cloudinaryService->uploadQrCode($ticket->hash);

            return $this->ticketRepository->update($ticket, [
                'qr_code_url' => $upload['url'],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to generate or upload ticket QR code', [
                'order_id' => $ticket->order_id,
                'ticket_id' => $ticket->id,
                'buyer_email' => $ticket->buyer_email,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function expectedTicketCount(Order $order): int
    {
        return (int) $order->items->sum(fn (OrderItem $item) => $item->seat_id ? 1 : $item->quantity);
    }
}

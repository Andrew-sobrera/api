<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Ticket;
use App\Repositories\TicketRepository;
use Illuminate\Support\Str;
use App\Support\QueueNames;
use Illuminate\Support\Facades\DB;

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
        if ($this->ticketRepository->existsForOrder($order->id)) {
            return;
        }

        $order->load(['items.eventTicket', 'items.seat', 'items.batch', 'items.sector', 'user', 'event']);

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $units = $item->seat_id ? 1 : $item->quantity;

                for ($i = 0; $i < $units; $i++) {
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

                    $upload = $this->cloudinaryService->uploadQrCode($hash);

                    $this->ticketRepository->update($ticket, [
                        'hash' => $hash,
                        'qr_code_url' => $upload['url'],
                    ]);

                    if ($item->seat_id) {
                        $this->seatService->confirmSeat($item->seat_id);
                    }
                }
            }
        });
    }
}

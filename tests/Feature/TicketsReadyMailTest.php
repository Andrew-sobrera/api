<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Jobs\SendTicketsEmailJob;
use App\Mail\TicketsReadyMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\TicketHashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketsReadyMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_tickets_ready_email_attaches_virtual_ticket_html_files(): void
    {
        $user = User::factory()->create(['email' => 'buyer@test.com']);
        $event = Event::factory()->create(['name' => 'Show Teste']);
        $ticketEvent = TicketEvent::factory()->create([
            'event_id' => $event->id,
            'name' => 'Pista',
            'quantity' => 5,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => OrderStatus::PAID,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 10000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'event_ticket_id' => $ticketEvent->id,
            'quantity' => 2,
            'unit_price' => 5000,
            'total_price' => 10000,
        ]);

        $hashService = app(TicketHashService::class);

        foreach (range(1, 2) as $i) {
            $ticket = Ticket::create([
                'order_id' => $order->id,
                'event_id' => $event->id,
                'event_ticket_id' => $ticketEvent->id,
                'buyer_name' => $user->name,
                'buyer_email' => $user->email,
                'status' => TicketStatus::GENERATED,
                'hash' => (string) \Illuminate\Support\Str::uuid(),
                'qr_code_url' => 'https://res.cloudinary.com/test/qr-'.$i.'.png',
            ]);
            $ticket->update(['hash' => $hashService->generate($event, $user->email, $ticket->id)]);
        }

        $order = $order->fresh([
            'user',
            'event',
            'issuedTickets.eventTicket',
            'issuedTickets.event',
            'issuedTickets.sector',
            'issuedTickets.seat',
        ]);

        $mail = new TicketsReadyMail($order);
        $attachments = $mail->attachments();

        $this->assertCount(3, $attachments, 'Esperado: 1 arquivo do pedido + 1 por ingresso');

        Mail::fake();

        (new SendTicketsEmailJob($order->id))->handle();

        Mail::assertSent(TicketsReadyMail::class, function (TicketsReadyMail $sent) use ($order) {
            return $sent->order->is($order)
                && count($sent->attachments()) === 1 + $order->issuedTickets->count();
        });
    }
}

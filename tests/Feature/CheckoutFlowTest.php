<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Jobs\CreatePaymentJob;
use App\Jobs\ExpireTicketReservationJob;
use App\Jobs\GenerateTicketsJob;
use App\Jobs\ProcessAsaasWebhookJob;
use App\Jobs\SendPurchaseEmailJob;
use App\Mail\PaymentApprovedMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PurchaseCreatedMail;
use App\Models\Order;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\OrderPaymentService;
use App\Services\PaymentWebhookService;
use App\Services\TicketReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createTicket(int $quantity = 50): TicketEvent
    {
        return TicketEvent::factory()->create([
            'quantity' => $quantity,
            'price' => 5000,
        ]);
    }

    private function authenticateUser(): User
    {
        $user = User::factory()->create(['document' => '12345678901']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function fakeAsaasPix(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/customers') && $request->method() === 'GET') {
                return Http::response(['data' => []]);
            }

            if (str_contains($request->url(), '/customers') && $request->method() === 'POST') {
                return Http::response(['id' => 'cus_123']);
            }

            if (str_contains($request->url(), '/pixQrCode')) {
                return Http::response([
                    'payload' => '00020126580014br.gov.bcb.pix',
                    'encodedImage' => 'iVBORw0KGgoAAAANSUhEUg',
                ]);
            }

            if (str_contains($request->url(), '/payments') && $request->method() === 'POST') {
                return Http::response(['id' => 'pay_pix_123', 'status' => 'PENDING']);
            }

            return Http::response([], 404);
        });
    }

    private function fakeAsaasCreditCard(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/customers') && $request->method() === 'GET') {
                return Http::response(['data' => []]);
            }

            if (str_contains($request->url(), '/customers') && $request->method() === 'POST') {
                return Http::response(['id' => 'cus_123']);
            }

            if (str_contains($request->url(), '/payments') && $request->method() === 'POST') {
                return Http::response(['id' => 'pay_cc_123', 'status' => 'PENDING']);
            }

            return Http::response([], 404);
        });
    }

    public function test_checkout_pix_with_success(): void
    {
        Queue::fake([
            CreatePaymentJob::class,
            SendPurchaseEmailJob::class,
            ExpireTicketReservationJob::class,
        ]);

        $this->fakeAsaasPix();
        $user = $this->authenticateUser();
        $ticket = $this->createTicket(50);

        $response = $this->postJson('/api/checkout', [
            'event_ticket_id' => $ticket->id,
            'quantity' => 2,
            'payment_method' => 'PIX',
        ]);

        $response->assertCreated()
            ->assertJson([
                'payment_method' => 'PIX',
                'status' => 'PENDING',
                'pix_payload' => '00020126580014br.gov.bcb.pix',
            ])
            ->assertJsonStructure(['order_id', 'pix_qr_code_url']);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'payment_method' => PaymentMethod::PIX->value,
            'payment_status' => PaymentStatus::PENDING->value,
            'asaas_payment_id' => 'pay_pix_123',
            'pix_payload' => '00020126580014br.gov.bcb.pix',
        ]);

        $this->assertEquals(48, $ticket->fresh()->quantity);
    }

    public function test_checkout_credit_card_with_valid_token(): void
    {
        Queue::fake([
            CreatePaymentJob::class,
            SendPurchaseEmailJob::class,
            ExpireTicketReservationJob::class,
        ]);

        $this->fakeAsaasCreditCard();
        $this->authenticateUser();
        $ticket = $this->createTicket(10);

        $response = $this->postJson('/api/checkout', [
            'event_ticket_id' => $ticket->id,
            'quantity' => 1,
            'payment_method' => 'CREDIT_CARD',
            'card_token' => 'tok_valid_123',
        ]);

        $response->assertCreated()
            ->assertJson([
                'payment_method' => 'CREDIT_CARD',
                'status' => 'PENDING',
            ])
            ->assertJsonStructure(['order_id'])
            ->assertJsonMissing(['pix_payload', 'pix_qr_code_url']);

        $this->assertDatabaseHas('orders', [
            'asaas_payment_id' => 'pay_cc_123',
            'payment_method' => PaymentMethod::CREDIT_CARD->value,
        ]);
    }

    public function test_checkout_credit_card_without_token(): void
    {
        $this->authenticateUser();
        $ticket = $this->createTicket(10);

        $response = $this->postJson('/api/checkout', [
            'event_ticket_id' => $ticket->id,
            'quantity' => 1,
            'payment_method' => 'CREDIT_CARD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['card_token']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pix_payment_saves_qr_code(): void
    {
        $this->fakeAsaasPix();

        $user = User::factory()->create(['document' => '12345678901']);
        $ticket = $this->createTicket(10);

        $order = Order::create([
            'user_id' => $user->id,
            'event_id' => $ticket->event_id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 5000,
        ]);

        app(OrderPaymentService::class)->processPayment($order->id);

        $order->refresh();

        $this->assertSame('pay_pix_123', $order->asaas_payment_id);
        $this->assertSame('00020126580014br.gov.bcb.pix', $order->pix_payload);
        $this->assertStringStartsWith('data:image/png;base64,', $order->pix_qr_code_url);
    }

    public function test_credit_card_payment_saves_asaas_payment_id(): void
    {
        $this->fakeAsaasCreditCard();

        $user = User::factory()->create(['document' => '12345678901']);
        $ticket = $this->createTicket(10);

        $order = Order::create([
            'user_id' => $user->id,
            'event_id' => $ticket->event_id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::CREDIT_CARD,
            'total_amount' => 5000,
        ]);

        app(OrderPaymentService::class)->processPayment($order->id, 'tok_valid');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'asaas_payment_id' => 'pay_cc_123',
        ]);
    }

    public function test_checkout_without_stock(): void
    {
        $this->authenticateUser();
        $ticket = $this->createTicket(0);

        $response = $this->postJson('/api/checkout', [
            'event_ticket_id' => $ticket->id,
            'quantity' => 1,
            'payment_method' => 'PIX',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Insufficient ticket stock available.',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_concurrent_checkout_prevents_overselling_last_ticket(): void
    {
        Queue::fake([
            CreatePaymentJob::class,
            SendPurchaseEmailJob::class,
            ExpireTicketReservationJob::class,
        ]);

        $this->fakeAsaasPix();
        $this->authenticateUser();
        $ticket = $this->createTicket(1);

        $first = $this->postJson('/api/checkout', [
            'event_ticket_id' => $ticket->id,
            'quantity' => 1,
            'payment_method' => 'PIX',
        ]);

        $second = $this->postJson('/api/checkout', [
            'event_ticket_id' => $ticket->id,
            'quantity' => 1,
            'payment_method' => 'PIX',
        ]);

        $first->assertCreated();
        $second->assertStatus(409);
        $this->assertEquals(0, $ticket->fresh()->quantity);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_webhook_payment_approved(): void
    {
        $user = User::factory()->create(['document' => '12345678901']);
        $ticket = $this->createTicket(5);

        $order = Order::create([
            'user_id' => $user->id,
            'event_id' => $ticket->event_id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 5000,
            'asaas_payment_id' => 'pay_approved',
        ]);

        $reservation = $order->reservation()->create([
            'event_ticket_id' => $ticket->id,
            'quantity' => 1,
            'status' => ReservationStatus::RESERVED,
            'expires_at' => now()->addMinutes(15),
        ]);

        Queue::fake([SendPurchaseEmailJob::class, GenerateTicketsJob::class]);

        app(PaymentWebhookService::class)->process('pay_approved', 'CONFIRMED');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PAID->value,
            'payment_status' => PaymentStatus::PAID->value,
        ]);

        $this->assertDatabaseHas('ticket_reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        Queue::assertPushed(GenerateTicketsJob::class, fn (GenerateTicketsJob $job) => $job->orderId === $order->id);
    }

    public function test_webhook_payment_failed(): void
    {
        $user = User::factory()->create(['document' => '12345678901']);
        $ticket = $this->createTicket(5);

        $order = Order::create([
            'user_id' => $user->id,
            'event_id' => $ticket->event_id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 5000,
            'asaas_payment_id' => 'pay_failed',
        ]);

        $order->reservation()->create([
            'event_ticket_id' => $ticket->id,
            'quantity' => 1,
            'status' => ReservationStatus::RESERVED,
            'expires_at' => now()->addMinutes(15),
        ]);

        $initialQuantity = $ticket->quantity;

        Queue::fake([SendPurchaseEmailJob::class]);

        app(PaymentWebhookService::class)->process('pay_failed', 'FAILED');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PAYMENT_FAILED->value,
            'payment_status' => PaymentStatus::FAILED->value,
        ]);

        $this->assertEquals($initialQuantity + 1, $ticket->fresh()->quantity);
    }

    public function test_reservation_expiration(): void
    {
        $user = User::factory()->create(['document' => '12345678901']);
        $ticket = $this->createTicket(0);

        $order = Order::create([
            'user_id' => $user->id,
            'event_id' => $ticket->event_id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 5000,
        ]);

        $reservation = $order->reservation()->create([
            'event_ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => ReservationStatus::RESERVED,
            'expires_at' => now()->subMinute(),
        ]);

        app(TicketReservationService::class)->expireReservation($reservation->id);

        $this->assertDatabaseHas('ticket_reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::EXPIRED->value,
        ]);

        $this->assertEquals(2, $ticket->fresh()->quantity);
    }

    public function test_payment_job_supports_rabbitmq_retry(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/customers') && $request->method() === 'GET') {
                return Http::response(['data' => []]);
            }

            if (str_contains($request->url(), '/customers') && $request->method() === 'POST') {
                return Http::response(['id' => 'cus_123']);
            }

            if (str_contains($request->url(), '/payments')) {
                return Http::response(['error' => 'temporary failure'], 500);
            }

            return Http::response([], 404);
        });

        $user = User::factory()->create(['document' => '12345678901']);
        $ticket = $this->createTicket(10);

        $order = Order::create([
            'user_id' => $user->id,
            'event_id' => $ticket->event_id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 5000,
        ]);

        $job = new CreatePaymentJob($order->id);

        $this->assertSame(5, $job->tries);
        $this->assertSame([10, 30, 60, 120, 300], $job->backoff);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $job->handle(app(OrderPaymentService::class));
    }

    public function test_webhook_endpoint_dispatches_queue_job(): void
    {
        Queue::fake();

        config(['asaas.webhook_token' => 'secret-token']);

        $response = $this->postJson('/api/webhooks/asaas', [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_webhook',
                'status' => 'CONFIRMED',
            ],
        ], [
            'asaas-access-token' => 'secret-token',
        ]);

        $response->assertOk();

        Queue::assertPushed(ProcessAsaasWebhookJob::class, function (ProcessAsaasWebhookJob $job) {
            return $job->paymentId === 'pay_webhook'
                && $job->status === 'CONFIRMED';
        });
    }

    public function test_checkout_dispatches_purchase_created_email_job(): void
    {
        Queue::fake([
            CreatePaymentJob::class,
            SendPurchaseEmailJob::class,
            ExpireTicketReservationJob::class,
        ]);

        $this->fakeAsaasPix();
        $this->authenticateUser();
        $ticket = $this->createTicket(10);

        $this->postJson('/api/checkout', [
            'event_ticket_id' => $ticket->id,
            'quantity' => 1,
            'payment_method' => 'PIX',
        ])->assertCreated();

        Queue::assertPushed(SendPurchaseEmailJob::class, function (SendPurchaseEmailJob $job) {
            return $job->mailClass === PurchaseCreatedMail::class;
        });
    }
}

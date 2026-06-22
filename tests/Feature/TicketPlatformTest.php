<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\TicketStatus;
use App\Jobs\GenerateTicketsJob;
use App\Models\Event;
use App\Models\EventSector;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\TicketBatch;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Services\PaymentWebhookService;
use App\Services\TicketHashService;
use App\Services\TicketValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class TicketPlatformTest extends TestCase
{
    use RefreshDatabase;

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
                return Http::response(['payload' => 'pix', 'encodedImage' => 'img']);
            }
            if (str_contains($request->url(), '/payments') && $request->method() === 'POST') {
                return Http::response(['id' => 'pay_test', 'status' => 'PENDING']);
            }

            return Http::response([], 404);
        });
    }

    public function test_create_event_with_multiple_sectors_and_seats(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/events', [
            'name' => 'Teatro Multi Setor',
            'date' => now()->addWeek()->toDateTimeString(),
            'category' => 'show',
            'status' => 'active',
            'ticket_type' => 'sector',
            'has_seats' => true,
            'sectors' => [
                ['name' => 'Pista', 'quantity' => 50, 'price' => 10000],
                ['name' => 'VIP', 'quantity' => 50, 'price' => 20000],
                ['name' => 'Setor 3', 'quantity' => 50, 'price' => 30000],
            ],
            'seats_config' => [
                'sectors' => [
                    ['name' => 'Pista', 'rows' => 5, 'seats_per_row' => 10],
                    ['name' => 'VIP', 'rows' => 5, 'seats_per_row' => 10],
                    ['name' => 'Setor 3', 'rows' => 5, 'seats_per_row' => 10],
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('seats', 150);
        $this->assertDatabaseHas('seats', ['label' => 'A1', 'status' => 'available']);
    }

    public function test_create_event_with_sectors(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/events', [
            'name' => 'Show Setores',
            'date' => now()->addWeek()->toDateTimeString(),
            'category' => 'show',
            'status' => 'active',
            'ticket_type' => 'sector',
            'has_seats' => false,
            'sectors' => [
                ['name' => 'Pista', 'quantity' => 100, 'price' => 5000],
                ['name' => 'VIP', 'quantity' => 50, 'price' => 15000],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('ticket_type', 'sector')
            ->assertJsonCount(2, 'sectors');

        $this->assertDatabaseCount('event_sectors', 2);
        $this->assertDatabaseCount('ticket_events', 2);
    }

    public function test_create_event_with_batches(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/events', [
            'name' => 'Festival Lotes',
            'date' => now()->addWeek()->toDateTimeString(),
            'category' => 'festival',
            'status' => 'active',
            'ticket_type' => 'batch',
            'batches' => [
                ['name' => '1º lote', 'quantity' => 100, 'price' => 8000],
                ['name' => '2º lote', 'quantity' => 200, 'price' => 12000],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('ticket_type', 'batch');

        $this->assertDatabaseHas('ticket_batches', [
            'name' => '1º lote',
            'status' => BatchStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('ticket_batches', [
            'name' => '2º lote',
            'status' => BatchStatus::PENDING->value,
        ]);
    }

    public function test_sector_with_batches_pricing(): void
    {
        $this->authenticateUser();

        $this->postJson('/api/events', [
            'name' => 'VIP Lotes',
            'date' => now()->addWeek()->toDateTimeString(),
            'category' => 'show',
            'status' => 'active',
            'ticket_type' => 'sector',
            'sectors' => [
                [
                    'name' => 'VIP',
                    'quantity' => 300,
                    'price' => 30000,
                    'batches' => [
                        ['name' => 'VIP - 1º lote', 'quantity' => 100, 'price' => 25000],
                        ['name' => 'VIP - 2º lote', 'quantity' => 200, 'price' => 30000],
                    ],
                ],
            ],
        ])->assertCreated();

        $batch = TicketBatch::where('name', 'VIP - 1º lote')->first();
        $this->assertNotNull($batch);
        $this->assertEquals(25000, $batch->price);
        $this->assertEquals(BatchStatus::ACTIVE, $batch->status);
    }

    public function test_cart_and_checkout_multi_item(): void
    {
        Queue::fake();
        $this->fakeAsaasPix();
        $user = $this->authenticateUser();

        $event = Event::factory()->create(['ticket_type' => 'sector', 'status' => 'active']);
        $sectorA = EventSector::create(['event_id' => $event->id, 'name' => 'Pista', 'sort_order' => 0, 'status' => 'active']);
        $sectorB = EventSector::create(['event_id' => $event->id, 'name' => 'VIP', 'sort_order' => 1, 'status' => 'active']);

        $ticketA = TicketEvent::factory()->create([
            'event_id' => $event->id,
            'sector_id' => $sectorA->id,
            'name' => 'Pista',
            'price' => 5000,
            'quantity' => 20,
        ]);
        $ticketB = TicketEvent::factory()->create([
            'event_id' => $event->id,
            'sector_id' => $sectorB->id,
            'name' => 'VIP',
            'price' => 10000,
            'quantity' => 10,
        ]);

        $this->postJson('/api/cart', [
            'event_ticket_id' => $ticketA->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->postJson('/api/cart', [
            'event_ticket_id' => $ticketB->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->getJson('/api/cart')->assertOk()->assertJsonCount(2);

        $response = $this->postJson('/api/checkout', [
            'from_cart' => true,
            'event_id' => $event->id,
            'payment_method' => 'PIX',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total_amount' => 20000,
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_seated_event_checkout(): void
    {
        Queue::fake();
        $this->fakeAsaasPix();
        $this->authenticateUser();

        $event = Event::factory()->create(['has_seats' => true, 'ticket_type' => 'sector', 'status' => 'active']);
        $sector = EventSector::create(['event_id' => $event->id, 'name' => 'VIP', 'sort_order' => 0, 'status' => 'active']);
        $ticket = TicketEvent::factory()->create([
            'event_id' => $event->id,
            'sector_id' => $sector->id,
            'price' => 20000,
            'quantity' => 10,
        ]);
        $seat = Seat::create([
            'event_id' => $event->id,
            'sector_id' => $sector->id,
            'row_label' => 'A',
            'seat_number' => '10',
            'label' => 'A10',
            'status' => SeatStatus::AVAILABLE,
        ]);

        $this->postJson('/api/checkout', [
            'event_ticket_id' => $ticket->id,
            'seat_id' => $seat->id,
            'quantity' => 1,
            'payment_method' => 'PIX',
        ])->assertCreated();

        $this->assertDatabaseHas('seats', ['id' => $seat->id, 'status' => SeatStatus::RESERVED->value]);
    }

    public function test_ticket_hash_generation_and_validation(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $event = Event::factory()->create();
        $ticketEvent = TicketEvent::factory()->create(['event_id' => $event->id]);
        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => OrderStatus::PAID,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 5000,
        ]);

        $hashService = app(TicketHashService::class);
        $ticket = Ticket::create([
            'order_id' => $order->id,
            'event_id' => $event->id,
            'event_ticket_id' => $ticketEvent->id,
            'buyer_name' => 'Buyer',
            'buyer_email' => 'buyer@example.com',
            'hash' => (string) \Illuminate\Support\Str::uuid(),
            'status' => TicketStatus::GENERATED,
        ]);

        $hash = $hashService->generate($event, 'buyer@example.com', $ticket->id);
        $ticket->update(['hash' => $hash]);

        $this->assertStringContainsString('.'.$ticket->id, $hash);
        $this->assertTrue($hashService->validate($ticket->fresh(), $hash));
    }

    public function test_ticket_generation_on_payment_approval(): void
    {
        $cloudinary = Mockery::mock(CloudinaryService::class);
        $cloudinary->shouldReceive('uploadQrCode')->andReturn([
            'url' => 'https://res.cloudinary.com/test/qr.png',
            'public_id' => 'tickets/qr/test',
        ]);
        $this->instance(CloudinaryService::class, $cloudinary);

        $user = User::factory()->create(['email' => 'buyer@test.com']);
        $event = Event::factory()->create();
        $ticketEvent = TicketEvent::factory()->create(['event_id' => $event->id, 'quantity' => 5]);

        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 10000,
            'asaas_payment_id' => 'pay_gen_test',
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'event_ticket_id' => $ticketEvent->id,
            'quantity' => 2,
            'unit_price' => 5000,
            'total_price' => 10000,
        ]);

        app(PaymentWebhookService::class)->process('pay_gen_test', 'CONFIRMED');

        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseHas('tickets', [
            'buyer_email' => 'buyer@test.com',
            'status' => TicketStatus::GENERATED->value,
            'qr_code_url' => 'https://res.cloudinary.com/test/qr.png',
        ]);
    }

    public function test_ticket_generation_retries_missing_qr_without_duplicating_tickets(): void
    {
        $cloudinary = Mockery::mock(CloudinaryService::class);
        $cloudinary->shouldReceive('uploadQrCode')
            ->once()
            ->andReturn([
                'url' => 'https://res.cloudinary.com/test/qr.svg',
                'public_id' => 'tickets/qr/retry',
            ]);
        $this->instance(CloudinaryService::class, $cloudinary);

        $user = User::factory()->create(['email' => 'retry@test.com']);
        $event = Event::factory()->create();
        $ticketEvent = TicketEvent::factory()->create(['event_id' => $event->id, 'quantity' => 5]);

        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => OrderStatus::PAID,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 5000,
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'event_ticket_id' => $ticketEvent->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
        ]);

        $hashService = app(TicketHashService::class);
        $ticket = Ticket::create([
            'order_id' => $order->id,
            'event_id' => $event->id,
            'event_ticket_id' => $ticketEvent->id,
            'buyer_name' => $user->name,
            'buyer_email' => $user->email,
            'status' => TicketStatus::GENERATED,
            'hash' => (string) \Illuminate\Support\Str::uuid(),
            'qr_code_url' => null,
        ]);
        $ticket->update(['hash' => $hashService->generate($event, $user->email, $ticket->id)]);

        app(\App\Services\TicketGenerationService::class)->generateForOrder($order->fresh(['items', 'user', 'event']));

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'qr_code_url' => 'https://res.cloudinary.com/test/qr.svg',
        ]);
    }

    public function test_ticket_validation_endpoint(): void
    {
        $cloudinary = Mockery::mock(CloudinaryService::class);
        $cloudinary->shouldReceive('uploadQrCode')->andReturn(['url' => 'https://qr.test', 'public_id' => 'x']);
        $this->instance(CloudinaryService::class, $cloudinary);

        $user = User::factory()->create(['email' => 'valid@test.com']);
        $event = Event::factory()->create();
        $ticketEvent = TicketEvent::factory()->create(['event_id' => $event->id]);

        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => PaymentMethod::PIX,
            'total_amount' => 5000,
            'asaas_payment_id' => 'pay_val',
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'event_ticket_id' => $ticketEvent->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
        ]);

        app(PaymentWebhookService::class)->process('pay_val', 'CONFIRMED');

        $ticket = Ticket::first();
        $this->assertNotNull($ticket);

        $this->postJson('/api/tickets/validate', ['code' => $ticket->hash])
            ->assertOk()
            ->assertJsonPath('valid', true);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::USED->value,
        ]);

        $this->postJson('/api/tickets/validate', ['code' => $ticket->hash])
            ->assertStatus(422)
            ->assertJsonPath('valid', false);
    }

    public function test_public_seat_map_returns_geometry(): void
    {
        $this->authenticateUser();

        $create = $this->postJson('/api/events', [
            'name' => 'Mapa Geometrico',
            'date' => now()->addWeek()->toDateTimeString(),
            'category' => 'show',
            'status' => 'active',
            'ticket_type' => 'sector',
            'has_seats' => true,
            'sectors' => [
                ['name' => 'Pista', 'quantity' => 20, 'price' => 10000],
            ],
            'seats_config' => [
                'sectors' => [
                    ['name' => 'Pista', 'rows' => 2, 'seats_per_row' => 3, 'color' => '#003366'],
                ],
            ],
        ]);

        $create->assertCreated();
        $event = Event::query()->where('name', 'Mapa Geometrico')->firstOrFail();
        $event->update(['slug' => 'mapa-geometrico']);
        $event->refresh();

        $this->assertDatabaseHas('venue_maps', ['event_id' => $event->id]);
        $this->assertDatabaseHas('seat_rows', ['name' => 'A']);
        $this->assertDatabaseHas('seats', ['label' => 'A1', 'pos_x' => 40]);

        $response = $this->getJson("/api/public/events/{$event->slug}/seat-map");

        $response->assertOk()
            ->assertJsonPath('venue_map.stage_label', 'PALCO')
            ->assertJsonCount(6, 'seats')
            ->assertJsonStructure([
                'venue_map' => ['width', 'height'],
                'sectors' => [['id', 'name', 'color', 'rows']],
                'seats' => [['id', 'pos_x', 'pos_y', 'seat_type', 'status']],
            ]);
    }

    public function test_seat_hold_and_release(): void
    {
        $event = Event::factory()->create(['has_seats' => true, 'status' => 'active', 'slug' => 'hold-test']);
        $sector = EventSector::create([
            'event_id' => $event->id,
            'name' => 'Pista',
            'sort_order' => 0,
            'status' => \App\Enums\SectorStatus::ACTIVE,
        ]);
        $seat = Seat::create([
            'event_id' => $event->id,
            'sector_id' => $sector->id,
            'row_label' => 'A',
            'seat_number' => '1',
            'label' => 'A1',
            'status' => SeatStatus::AVAILABLE,
        ]);

        $hold = $this->postJson('/api/seats/hold', [
            'seat_ids' => [$seat->id],
            'session_id' => 'test-session',
        ]);

        $hold->assertOk()->assertJsonPath('held.0.seat_id', $seat->id);

        $map = $this->getJson("/api/public/events/{$event->slug}/seat-map");
        $map->assertOk()->assertJsonPath('seats.0.status', 'held');

        $this->postJson('/api/seats/release-holds', [
            'seat_ids' => [$seat->id],
            'session_id' => 'test-session',
        ])->assertOk();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

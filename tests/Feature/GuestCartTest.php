<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSector;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestCartTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_can_create_cart_and_add_items(): void
    {
        Queue::fake();

        $event = Event::factory()->create(['ticket_type' => 'sector', 'status' => 'active']);
        $sector = EventSector::create(['event_id' => $event->id, 'name' => 'Pista', 'sort_order' => 0, 'status' => 'active']);
        $ticket = TicketEvent::factory()->create([
            'event_id' => $event->id,
            'sector_id' => $sector->id,
            'name' => 'Pista',
            'price' => 5000,
            'quantity' => 20,
        ]);

        $response = $this->postJson('/api/cart/items', [
            'ticket_id' => $ticket->id,
            'quantity' => 2,
        ])->assertCreated();

        $response->assertJsonStructure([
            'cart_id',
            'expires_at',
            'expires_in_seconds',
            'item_count',
            'total_amount',
            'items',
        ]);

        $cartId = $response->json('cart_id');

        $this->getJson("/api/cart/{$cartId}", [
            'X-Cart-Id' => $cartId,
        ])->assertOk()->assertJsonPath('item_count', 2);

        $this->assertDatabaseHas('carts', [
            'uuid' => $cartId,
            'user_id' => null,
            'status' => 'active',
        ]);
    }

    public function test_guest_cart_merges_on_login(): void
    {
        Queue::fake();

        $event = Event::factory()->create(['ticket_type' => 'sector', 'status' => 'active']);
        $sector = EventSector::create(['event_id' => $event->id, 'name' => 'Pista', 'sort_order' => 0, 'status' => 'active']);
        $ticket = TicketEvent::factory()->create([
            'event_id' => $event->id,
            'sector_id' => $sector->id,
            'price' => 5000,
            'quantity' => 20,
        ]);

        $guestResponse = $this->postJson('/api/cart/items', [
            'ticket_id' => $ticket->id,
            'quantity' => 1,
        ])->assertCreated();

        $cartId = $guestResponse->json('cart_id');
        $user = $this->authenticateUser();

        $this->postJson('/api/cart/merge', ['cart_id' => $cartId])
            ->assertOk()
            ->assertJsonPath('item_count', 1);

        $this->assertDatabaseHas('carts', [
            'uuid' => $cartId,
            'user_id' => $user->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSector;
use App\Models\Seat;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueMap;
use App\Models\VenueMapVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeatMapExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateUser(): User
    {
        $user = User::factory()->create(['document' => '12345678901']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function createSeatedEvent(string $name = 'Evento Mapa'): Event
    {
        $this->authenticateUser();

        $this->postJson('/api/events', [
            'name' => $name,
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
        ])->assertCreated();

        return Event::query()->where('name', $name)->firstOrFail();
    }

    public function test_generate_row_creates_seats(): void
    {
        $event = $this->createSeatedEvent();
        $sector = EventSector::query()->where('event_id', $event->id)->firstOrFail();

        $beforeCount = Seat::query()->where('event_id', $event->id)->count();

        $response = $this->postJson("/api/events/{$event->id}/seat-map/generate-row", [
            'sector_id' => $sector->id,
            'count' => 5,
            'spacing' => 36,
            'start_x' => 200,
            'start_y' => 300,
            'row_label' => 'C',
            'naming_scheme' => 'row_letter',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['seat_row_id', 'seats' => [['id', 'label', 'pos_x', 'pos_y']]])
            ->assertJsonCount(5, 'seats');

        $this->assertSame($beforeCount + 5, Seat::query()->where('event_id', $event->id)->count());
        $this->assertDatabaseHas('seats', ['event_id' => $event->id, 'label' => 'C1', 'pos_x' => 200]);
    }

    public function test_create_and_delete_seats(): void
    {
        $event = $this->createSeatedEvent();
        $sector = EventSector::query()->where('event_id', $event->id)->firstOrFail();

        $create = $this->postJson("/api/events/{$event->id}/seat-map/seats", [
            'seats' => [
                [
                    'sector_id' => $sector->id,
                    'row_label' => 'Z',
                    'seat_number' => '9',
                    'label' => 'Z9',
                    'pos_x' => 500,
                    'pos_y' => 500,
                    'seat_type' => 'vip',
                ],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('seats.0.label', 'Z9')
            ->assertJsonPath('seats.0.seat_type', 'vip');

        $seatId = $create->json('seats.0.id');

        $this->deleteJson("/api/events/{$event->id}/seat-map/seats?seat_ids[]={$seatId}")
            ->assertOk();

        $this->assertDatabaseMissing('seats', ['id' => $seatId]);
    }

    public function test_seat_map_bbox_filters_seats(): void
    {
        $event = $this->createSeatedEvent();
        $event->update(['slug' => 'bbox-test']);

        Seat::query()->where('event_id', $event->id)->update(['pos_x' => 10, 'pos_y' => 10]);
        $farSeat = Seat::query()->where('event_id', $event->id)->firstOrFail();
        Seat::create([
            'event_id' => $event->id,
            'sector_id' => $farSeat->sector_id,
            'row_label' => 'Z',
            'seat_number' => '99',
            'label' => 'Z99',
            'pos_x' => 900,
            'pos_y' => 900,
            'status' => \App\Enums\SeatStatus::AVAILABLE,
        ]);

        $full = $this->getJson("/api/events/{$event->id}/seat-map");
        $full->assertOk()->assertJsonPath('bbox_filtered', false);

        $filtered = $this->getJson("/api/events/{$event->id}/seat-map?min_x=0&min_y=0&max_x=100&max_y=100");
        $filtered->assertOk()
            ->assertJsonPath('bbox_filtered', true)
            ->assertJsonPath('total_seats', 7);

        $this->assertLessThan(7, count($filtered->json('seats')));
    }

    public function test_venue_map_version_save_and_list(): void
    {
        $event = $this->createSeatedEvent();

        $save = $this->postJson("/api/events/{$event->id}/seat-map/versions", [
            'label' => 'Layout inicial',
        ]);

        $save->assertCreated()
            ->assertJsonPath('label', 'Layout inicial')
            ->assertJsonPath('version_number', 1);

        $list = $this->getJson("/api/events/{$event->id}/seat-map/versions");
        $list->assertOk()->assertJsonCount(1);
    }

    public function test_venue_map_version_restore(): void
    {
        $event = $this->createSeatedEvent();
        $seat = Seat::query()->where('event_id', $event->id)->where('label', 'A1')->firstOrFail();
        $originalX = (float) $seat->pos_x;

        $version = $this->postJson("/api/events/{$event->id}/seat-map/versions", [
            'label' => 'Antes do movimento',
        ])->assertCreated();

        Seat::query()->where('event_id', $event->id)->where('label', 'A1')->update(['pos_x' => $originalX + 100]);

        $this->postJson("/api/events/{$event->id}/seat-map/versions/{$version->json('id')}/restore")
            ->assertOk();

        $restored = Seat::query()->where('event_id', $event->id)->where('label', 'A1')->firstOrFail();
        $this->assertEquals($originalX, (float) $restored->pos_x);
        $this->assertDatabaseHas('venue_map_versions', [
            'venue_map_id' => VenueMap::query()->where('event_id', $event->id)->value('id'),
        ]);
    }

    public function test_venues_create_list_and_link_map(): void
    {
        $user = $this->authenticateUser();
        $event = $this->createSeatedEvent('Evento Origem');

        $venue = $this->postJson('/api/venues', [
            'name' => 'Teatro Municipal',
            'description' => 'Planta principal',
            'address' => 'Rua A, 100',
        ]);

        $venue->assertCreated()
            ->assertJsonPath('name', 'Teatro Municipal');

        $venueId = $venue->json('id');

        $this->getJson('/api/venues')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Teatro Municipal']);

        $link = $this->postJson("/api/venues/{$venueId}/maps/from-event", [
            'event_id' => $event->id,
        ]);

        $link->assertOk()->assertJsonPath('snapshot_sectors', 1);

        $this->assertDatabaseHas('venue_maps', [
            'event_id' => $event->id,
            'venue_id' => $venueId,
        ]);

        $this->postJson('/api/events', [
            'name' => 'Evento Destino',
            'date' => now()->addWeeks(2)->toDateTimeString(),
            'category' => 'show',
            'status' => 'active',
            'ticket_type' => 'sector',
            'has_seats' => true,
            'sectors' => [
                ['name' => 'Pista', 'quantity' => 20, 'price' => 10000],
            ],
            'seats_config' => [
                'sectors' => [
                    ['name' => 'Pista', 'rows' => 1, 'seats_per_row' => 1],
                ],
            ],
        ])->assertCreated();

        $target = Event::query()->where('name', 'Evento Destino')->firstOrFail();

        $this->postJson("/api/venues/{$venueId}/apply-to-event", [
            'event_id' => $target->id,
        ])->assertOk();

        $this->assertSame(
            Seat::query()->where('event_id', $event->id)->count(),
            Seat::query()->where('event_id', $target->id)->count(),
        );
    }

    public function test_apply_venue_without_map_returns_error(): void
    {
        $this->authenticateUser();

        $venue = Venue::query()->create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Local vazio',
        ]);

        $event = Event::factory()->create(['has_seats' => true]);

        $this->postJson("/api/venues/{$venue->id}/apply-to-event", [
            'event_id' => $event->id,
        ])->assertStatus(422);
    }

    public function test_save_layout_with_floor_plan_and_vip_seat_type(): void
    {
        $event = $this->createSeatedEvent();
        $seat = Seat::query()->where('event_id', $event->id)->firstOrFail();

        $response = $this->putJson("/api/events/{$event->id}/seat-map", [
            'venue_map' => [
                'floor_plan_opacity' => 0.5,
                'floor_plan_scale_x' => 1.2,
                'floor_plan_visible' => true,
            ],
            'seats' => [
                ['id' => $seat->id, 'seat_type' => 'vip'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('venue_map.floor_plan_opacity', 0.5)
            ->assertJsonPath('seats.0.seat_type', 'vip');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventLocationTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function fakeGoogleGeocode(float $lat = -29.9744, float $lng = -51.1958): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'geometry' => [
                            'location' => [
                                'lat' => $lat,
                                'lng' => $lng,
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    private function baseEventPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Show na Arena',
            'date' => now()->addWeek()->toDateTimeString(),
            'category' => 'show',
            'status' => 'active',
            'ticket_type' => 'simple',
            'ticket' => [
                'price' => 100,
                'quantity' => 50,
            ],
        ], $overrides);
    }

    public function test_create_event_with_location_geocodes_address(): void
    {
        $this->authenticateUser();
        $this->fakeGoogleGeocode();

        config(['services.google_maps.key' => 'test-key']);

        $response = $this->postJson('/api/events', $this->baseEventPayload([
            'location_name' => 'Arena do Grêmio',
            'address' => 'Av. Padre Leopoldo Brentano, 110 - Porto Alegre - RS',
        ]));

        $response->assertCreated()
            ->assertJsonPath('location_name', 'Arena do Grêmio')
            ->assertJsonPath('address', 'Av. Padre Leopoldo Brentano, 110 - Porto Alegre - RS')
            ->assertJsonPath('latitude', -29.9744)
            ->assertJsonPath('longitude', -51.1958)
            ->assertJsonPath('google_maps_url', 'https://www.google.com/maps/search/?api=1&query=-29.9744,-51.1958');

        $this->assertDatabaseHas('events', [
            'location_name' => 'Arena do Grêmio',
            'latitude' => -29.9744,
            'longitude' => -51.1958,
        ]);
    }

    public function test_create_event_succeeds_when_geocoding_fails(): void
    {
        $this->authenticateUser();

        config(['services.google_maps.key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'results' => [],
            ]),
        ]);

        $response = $this->postJson('/api/events', $this->baseEventPayload([
            'location_name' => 'Local Teste',
            'address' => 'Endereço inválido xyz',
        ]));

        $response->assertCreated()
            ->assertJsonPath('location_name', 'Local Teste')
            ->assertJsonPath('latitude', null)
            ->assertJsonPath('longitude', null)
            ->assertJsonPath('google_maps_url', null);
    }

    public function test_update_event_location_regeocodes_when_address_changes(): void
    {
        $this->authenticateUser();
        $this->fakeGoogleGeocode(-30.0346, -51.2177);

        config(['services.google_maps.key' => 'test-key']);

        $createResponse = $this->postJson('/api/events', $this->baseEventPayload([
            'location_name' => 'Local Antigo',
            'address' => 'Endereço antigo',
        ]));

        $eventId = $createResponse->json('id');

        $updateResponse = $this->putJson("/api/events/{$eventId}", [
            'location_name' => 'Mercado Público',
            'address' => 'Mercado Público, Porto Alegre - RS',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('location_name', 'Mercado Público')
            ->assertJsonPath('latitude', -30.0346)
            ->assertJsonPath('longitude', -51.2177);
    }

    public function test_public_event_returns_location_fields(): void
    {
        $this->authenticateUser();

        Event::withoutGlobalScopes()->create([
            'name' => 'Evento Público',
            'slug' => 'evento-publico',
            'date' => now()->addWeek(),
            'category' => 'show',
            'status' => 'active',
            'ticket_type' => 'simple',
            'location_name' => 'Arena do Grêmio',
            'address' => 'Av. Padre Leopoldo Brentano, 110 - Porto Alegre - RS',
            'latitude' => -29.9744,
            'longitude' => -51.1958,
        ]);

        $response = $this->getJson('/api/public/events/evento-publico');

        $response->assertOk()
            ->assertJsonPath('location_name', 'Arena do Grêmio')
            ->assertJsonPath('address', 'Av. Padre Leopoldo Brentano, 110 - Porto Alegre - RS')
            ->assertJsonPath('latitude', -29.9744)
            ->assertJsonPath('longitude', -51.1958)
            ->assertJsonPath('google_maps_url', 'https://www.google.com/maps/search/?api=1&query=-29.9744,-51.1958');
    }
}

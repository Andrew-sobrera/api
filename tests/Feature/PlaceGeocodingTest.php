<?php

namespace Tests\Feature;

use App\Jobs\GeocodePlaceJob;
use App\Models\GeocodeCache;
use App\Models\Place;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaceGeocodingTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateProducer(): User
    {
        $producer = Producer::query()->create([
            'name' => 'Produtor Teste',
            'cnpj' => '12345678000199',
        ]);

        $user = User::factory()->create([
            'role' => 'producer',
            'producer_id' => $producer->id,
        ]);

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

    private function fakeOsmGeocode(float $lat = -30.0346, float $lng = -51.2177): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => (string) $lat,
                    'lon' => (string) $lng,
                ],
            ]),
        ]);
    }

    public function test_create_place_with_manual_coordinates(): void
    {
        Queue::fake();
        $this->authenticateProducer();

        $response = $this->postJson('/api/places', [
            'name' => 'Arena do Grêmio',
            'address' => 'Av. Padre Leopoldo Brentano, 110 - Porto Alegre - RS',
            'latitude' => -29.9744,
            'longitude' => -51.1958,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Arena do Grêmio')
            ->assertJsonPath('latitude', -29.9744)
            ->assertJsonPath('longitude', -51.1958)
            ->assertJsonPath('geocoding_status', 'completed')
            ->assertJsonPath('provider', 'manual');

        Queue::assertNothingPushed();
    }

    public function test_create_place_dispatches_geocode_job_when_no_coordinates(): void
    {
        Queue::fake();
        $this->authenticateProducer();

        $response = $this->postJson('/api/places', [
            'name' => 'Mercado Público',
            'address' => 'Mercado Público, Porto Alegre - RS',
        ]);

        $response->assertCreated()
            ->assertJsonPath('geocoding_status', 'pending');

        Queue::assertPushed(GeocodePlaceJob::class);
    }

    public function test_create_place_uses_geocode_cache(): void
    {
        $this->authenticateProducer();

        GeocodeCache::query()->create([
            'address_normalized' => 'mercado público, porto alegre - rs',
            'latitude' => -30.0346,
            'longitude' => -51.2177,
            'provider' => 'google',
        ]);

        Http::fake();

        $response = $this->postJson('/api/places', [
            'name' => 'Mercado Público',
            'address' => 'Mercado Público, Porto Alegre - RS',
        ]);

        $response->assertCreated()
            ->assertJsonPath('latitude', -30.0346)
            ->assertJsonPath('longitude', -51.2177)
            ->assertJsonPath('geocoding_status', 'completed');

        Http::assertNothingSent();
    }

    public function test_geocode_job_resolves_coordinates_via_google(): void
    {
        $user = $this->authenticateProducer();
        $this->fakeGoogleGeocode();
        config(['services.google_maps.key' => 'test-key']);

        $place = Place::query()->create([
            'producer_id' => $user->producer_id,
            'name' => 'Local Teste',
            'address' => 'Av. Padre Leopoldo Brentano, 110 - Porto Alegre - RS',
            'address_normalized' => 'av. padre leopoldo brentano, 110 - porto alegre - rs',
            'geocoding_status' => Place::STATUS_PENDING,
        ]);

        $job = new GeocodePlaceJob($place->id);
        $job->handle(app(\App\Services\PlaceService::class));

        $place->refresh();

        $this->assertSame(Place::STATUS_COMPLETED, $place->geocoding_status);
        $this->assertSame(-29.9744, $place->latitude);
        $this->assertSame(-51.1958, $place->longitude);
        $this->assertDatabaseHas('geocode_cache', [
            'address_normalized' => 'av. padre leopoldo brentano, 110 - porto alegre - rs',
            'provider' => 'google',
        ]);
    }

    public function test_geocode_falls_back_to_osm_when_google_fails(): void
    {
        $user = $this->authenticateProducer();
        config(['services.google_maps.key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'results' => [],
            ]),
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-30.0346', 'lon' => '-51.2177'],
            ]),
        ]);

        $place = Place::query()->create([
            'producer_id' => $user->producer_id,
            'name' => 'Local OSM',
            'address' => 'Mercado Público, Porto Alegre - RS',
            'address_normalized' => 'mercado público, porto alegre - rs',
            'geocoding_status' => Place::STATUS_PENDING,
        ]);

        $job = new GeocodePlaceJob($place->id);
        $job->handle(app(\App\Services\PlaceService::class));

        $place->refresh();

        $this->assertSame(Place::STATUS_COMPLETED, $place->geocoding_status);
        $this->assertSame('osm', $place->provider);
    }

    public function test_update_place_coordinates_creates_audit(): void
    {
        $user = $this->authenticateProducer();

        $place = Place::query()->create([
            'producer_id' => $user->producer_id,
            'name' => 'Local Audit',
            'address' => 'Endereço original',
            'address_normalized' => 'endereço original',
            'latitude' => -29.9744,
            'longitude' => -51.1958,
            'provider' => 'manual',
            'geocoding_status' => Place::STATUS_COMPLETED,
        ]);

        $response = $this->putJson("/api/places/{$place->id}", [
            'latitude' => -30.0346,
            'longitude' => -51.2177,
        ]);

        $response->assertOk()
            ->assertJsonPath('latitude', -30.0346);

        $this->assertDatabaseHas('place_audits', [
            'place_id' => $place->id,
            'old_lat' => -29.9744,
            'new_lat' => -30.0346,
            'changed_by' => $user->id,
        ]);
    }

    public function test_list_places_scoped_to_producer(): void
    {
        $user = $this->authenticateProducer();

        Place::withoutGlobalScopes()->create([
            'producer_id' => $user->producer_id,
            'name' => 'Meu Local',
            'address' => 'Rua A, 1',
            'address_normalized' => 'rua a, 1',
            'geocoding_status' => Place::STATUS_COMPLETED,
        ]);

        $otherProducer = Producer::query()->create([
            'name' => 'Outro',
            'cnpj' => '98765432000111',
        ]);

        Place::withoutGlobalScopes()->create([
            'producer_id' => $otherProducer->id,
            'name' => 'Local Alheio',
            'address' => 'Rua B, 2',
            'address_normalized' => 'rua b, 2',
            'geocoding_status' => Place::STATUS_COMPLETED,
        ]);

        $response = $this->getJson('/api/places');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Meu Local');
    }
}

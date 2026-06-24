<?php

namespace App\Jobs;

use App\Models\Place;
use App\Services\PlaceService;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeocodePlaceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [15, 30, 60, 120];

    public function __construct(public int $placeId)
    {
        $this->onQueue(QueueNames::GEOCODING);
    }

    public function handle(PlaceService $placeService): void
    {
        $place = Place::withoutGlobalScopes()->find($this->placeId);

        if (! $place) {
            Log::warning('GeocodePlaceJob: place not found.', ['place_id' => $this->placeId]);

            return;
        }

        if ($place->geocoding_status === Place::STATUS_COMPLETED) {
            return;
        }

        Log::info('GeocodePlaceJob started', ['place_id' => $this->placeId]);

        $placeService->resolveGeocoding($place);

        Log::info('GeocodePlaceJob completed', [
            'place_id' => $this->placeId,
            'status' => $place->fresh()->geocoding_status,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GeocodePlaceJob failed permanently', [
            'place_id' => $this->placeId,
            'message' => $exception?->getMessage(),
        ]);

        Place::withoutGlobalScopes()
            ->where('id', $this->placeId)
            ->where('geocoding_status', '!=', Place::STATUS_COMPLETED)
            ->update(['geocoding_status' => Place::STATUS_FAILED]);
    }
}

<?php

namespace App\Services;

use App\Models\Event;
use App\Models\VenueMap;
use App\Models\VenueMapVersion;
use Illuminate\Support\Facades\DB;

class VenueMapVersionService
{
    public function __construct(
        private readonly SeatMapService $seatMapService,
    ) {}

    public function listForEvent(int $eventId): array
    {
        $venueMap = VenueMap::query()->where('event_id', $eventId)->first();

        if (! $venueMap) {
            return [];
        }

        return VenueMapVersion::query()
            ->where('venue_map_id', $venueMap->id)
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (VenueMapVersion $version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'label' => $version->label,
                'published_at' => $version->published_at?->toIso8601String(),
                'created_at' => $version->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function saveVersion(int $eventId, string $label, ?int $userId = null): array
    {
        $event = Event::query()->findOrFail($eventId);
        $venueMap = VenueMap::query()->firstOrCreate(
            ['event_id' => $event->id],
            ['name' => 'Mapa principal', 'width' => 800, 'height' => 600]
        );

        $nextNumber = (int) VenueMapVersion::query()
            ->where('venue_map_id', $venueMap->id)
            ->max('version_number') + 1;

        $snapshot = $this->seatMapService->exportLayoutSnapshot($event->fresh());

        $version = VenueMapVersion::query()->create([
            'venue_map_id' => $venueMap->id,
            'user_id' => $userId,
            'version_number' => $nextNumber,
            'label' => $label,
            'snapshot' => $snapshot,
            'published_at' => now(),
        ]);

        $venueMap->update(['version' => $venueMap->version + 1]);

        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'label' => $version->label,
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }

    public function restoreVersion(int $eventId, int $versionId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($eventId, $versionId, $userId) {
            $event = Event::query()->findOrFail($eventId);
            $venueMap = VenueMap::query()->where('event_id', $event->id)->firstOrFail();

            $version = VenueMapVersion::query()
                ->where('venue_map_id', $venueMap->id)
                ->where('id', $versionId)
                ->firstOrFail();

            app(VenueMapTemplateService::class)->applySnapshotToEvent($event, $version->snapshot);

            return $this->saveVersion($eventId, 'Restaurado: '.$version->label, $userId);
        });
    }
}

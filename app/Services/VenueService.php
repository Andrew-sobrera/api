<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Venue;
use App\Models\VenueMap;
use Illuminate\Support\Facades\DB;

class VenueService
{
    public function __construct(
        private readonly VenueMapTemplateService $templateService,
    ) {}

    public function listForUser(?int $userId): array
    {
        return Venue::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('name')
            ->get()
            ->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
                'description' => $venue->description,
                'address' => $venue->address,
            ])
            ->values()
            ->all();
    }

    public function create(array $data, ?int $userId): array
    {
        $venue = Venue::query()->create([
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return [
            'id' => $venue->id,
            'name' => $venue->name,
            'description' => $venue->description,
            'address' => $venue->address,
        ];
    }

    public function saveMapFromEvent(int $venueId, int $eventId): array
    {
        $venue = Venue::query()->findOrFail($venueId);
        $event = Event::query()->findOrFail($eventId);

        $snapshot = app(SeatMapService::class)->exportLayoutSnapshot($event);

        $venueMap = VenueMap::query()->updateOrCreate(
            ['event_id' => $event->id],
            ['venue_id' => $venue->id, 'name' => $venue->name.' — mapa']
        );

        $venueMap->update(['venue_id' => $venue->id]);

        return [
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'snapshot_sectors' => count($snapshot['sectors'] ?? []),
        ];
    }

    public function applyVenueToEvent(int $venueId, int $eventId): void
    {
        DB::transaction(function () use ($venueId, $eventId) {
            $sourceEvent = VenueMap::query()
                ->where('venue_id', $venueId)
                ->whereNotNull('event_id')
                ->latest('updated_at')
                ->first();

            if (! $sourceEvent) {
                abort(422, 'Este local ainda não possui mapa vinculado.');
            }

            $targetEvent = Event::query()->findOrFail($eventId);
            $snapshot = app(SeatMapService::class)->exportLayoutSnapshot(
                Event::query()->findOrFail($sourceEvent->event_id)
            );

            $this->templateService->applySnapshotToEvent($targetEvent, $snapshot);
        });
    }
}

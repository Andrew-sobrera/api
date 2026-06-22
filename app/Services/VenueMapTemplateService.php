<?php

namespace App\Services;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Models\Event;
use App\Models\EventSector;
use App\Models\Seat;
use App\Models\SeatRow;
use App\Models\VenueMap;
use App\Models\VenueMapTemplate;
use Illuminate\Support\Facades\DB;

class VenueMapTemplateService
{
    public function listForUser(?int $userId): array
    {
        return VenueMapTemplate::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (VenueMapTemplate $template) => $this->formatSummary($template))
            ->values()
            ->all();
    }

    public function getById(int $id): array
    {
        $template = VenueMapTemplate::query()->findOrFail($id);

        return $this->formatDetail($template);
    }

    public function saveFromEvent(int $eventId, string $name, ?string $description, ?int $userId): array
    {
        $event = Event::query()
            ->with(['venueMap', 'sectors.seatRows.seats'])
            ->findOrFail($eventId);

        if (! $event->has_seats) {
            abort(422, 'Este evento não possui mapa de assentos.');
        }

        $layout = app(SeatMapService::class)->exportLayoutSnapshot($event);

        $template = VenueMapTemplate::query()->create([
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'layout' => $layout,
        ]);

        return $this->formatDetail($template);
    }

    public function delete(int $id): void
    {
        VenueMapTemplate::query()->where('id', $id)->delete();
    }

    public function applyToEvent(Event $event, int $templateId): void
    {
        $template = VenueMapTemplate::query()->findOrFail($templateId);
        $this->applySnapshotToEvent($event, $template->layout ?? []);
    }

    public function applySnapshotToEvent(Event $event, array $layout): void
    {
        DB::transaction(function () use ($event, $layout) {
            $event->load(['sectors', 'venueMap']);

            if (! empty($layout['venue'])) {
                $venue = VenueMap::query()->firstOrCreate(
                    ['event_id' => $event->id],
                    ['name' => 'Mapa principal', 'width' => 800, 'height' => 600]
                );

                $venueData = $layout['venue'];
                $venue->update([
                    'width' => $venueData['width'] ?? 800,
                    'height' => $venueData['height'] ?? 600,
                    'stage_label' => $venueData['stage_label'] ?? 'PALCO',
                    'stage_x' => $venueData['stage_x'] ?? null,
                    'stage_y' => $venueData['stage_y'] ?? null,
                    'stage_width' => $venueData['stage_width'] ?? 280,
                    'stage_height' => $venueData['stage_height'] ?? 36,
                    'floor_plan_url' => $venueData['floor_plan_url'] ?? null,
                    'floor_plan_opacity' => $venueData['floor_plan_opacity'] ?? 1,
                    'floor_plan_scale_x' => $venueData['floor_plan_scale_x'] ?? 1,
                    'floor_plan_scale_y' => $venueData['floor_plan_scale_y'] ?? 1,
                    'floor_plan_locked' => $venueData['floor_plan_locked'] ?? false,
                    'floor_plan_visible' => $venueData['floor_plan_visible'] ?? true,
                    'markers' => $venueData['markers'] ?? [],
                    'version' => ($venue->version ?? 0) + 1,
                ]);

                if (! empty($venueData['elements'])) {
                    app(SeatMapService::class)->syncElementsFromSnapshot($venue, $venueData['elements']);
                }
            }

            foreach ($layout['sectors'] ?? [] as $sectorSnapshot) {
                $sector = $event->sectors->firstWhere('name', $sectorSnapshot['name'] ?? '');

                if (! $sector) {
                    continue;
                }

                $sector->seatRows()->each(function (SeatRow $row) {
                    $row->seats()->delete();
                });
                $sector->seatRows()->delete();
                $sector->seats()->delete();

                $sector->update([
                    'color' => $sectorSnapshot['color'] ?? $sector->color,
                    'category' => $sectorSnapshot['category'] ?? $sector->category,
                    'pos_x' => $sectorSnapshot['pos_x'] ?? $sector->pos_x,
                    'pos_y' => $sectorSnapshot['pos_y'] ?? $sector->pos_y,
                    'map_visible' => true,
                ]);

                foreach ($sectorSnapshot['rows'] ?? [] as $rowSnapshot) {
                    $seatRow = SeatRow::create([
                        'sector_id' => $sector->id,
                        'name' => $rowSnapshot['name'],
                        'sort_order' => $rowSnapshot['sort_order'] ?? 0,
                        'pos_x' => $rowSnapshot['pos_x'] ?? 0,
                        'pos_y' => $rowSnapshot['pos_y'] ?? 0,
                    ]);

                    foreach ($rowSnapshot['seats'] ?? [] as $seatSnapshot) {
                        Seat::create([
                            'event_id' => $event->id,
                            'sector_id' => $sector->id,
                            'seat_row_id' => $seatRow->id,
                            'row_label' => $seatSnapshot['row_label'],
                            'seat_number' => $seatSnapshot['seat_number'],
                            'label' => $seatSnapshot['label'],
                            'pos_x' => $seatSnapshot['pos_x'] ?? 0,
                            'pos_y' => $seatSnapshot['pos_y'] ?? 0,
                            'rotation' => $seatSnapshot['rotation'] ?? 0,
                            'width' => $seatSnapshot['width'] ?? 28,
                            'height' => $seatSnapshot['height'] ?? 28,
                            'seat_type' => $seatSnapshot['seat_type'] ?? SeatType::STANDARD->value,
                            'status' => SeatStatus::AVAILABLE,
                        ]);
                    }
                }
            }

            app(SeatMapService::class)->invalidateCache($event->id);
        });
    }

    public function seatsConfigFromTemplate(int $templateId): array
    {
        $template = VenueMapTemplate::query()->findOrFail($templateId);

        return $template->layout['seats_config'] ?? ['sectors' => []];
    }

    private function formatSummary(VenueMapTemplate $template): array
    {
        $sectors = $template->layout['seats_config']['sectors'] ?? [];

        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'sector_count' => count($sectors),
            'seat_count' => collect($sectors)->sum(fn ($s) => array_sum($s['row_seats'] ?? [])),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    private function formatDetail(VenueMapTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'layout' => $template->layout,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}

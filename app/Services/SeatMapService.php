<?php

namespace App\Services;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Models\Event;
use App\Models\EventSector;
use App\Models\MapElement;
use App\Models\Seat;
use App\Models\SeatRow;
use App\Models\VenueMap;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeatMapService
{
    private const SEAT_SPACING = 36;

    private const ROW_SPACING = 40;

    private const SECTOR_GAP = 80;

    private const SEAT_SIZE = 28;

    public function __construct(
        private readonly SeatHoldService $seatHoldService,
        private readonly RowGenerationService $rowGenerationService,
    ) {}

    private const ALLOWED_SEAT_TYPES = [
        'standard', 'pcd', 'companion', 'blocked', 'vip', 'table', 'booth',
    ];

    public function generateFromConfig(Event $event, array $config): void
    {
        $venueMap = VenueMap::query()->firstOrCreate(
            ['event_id' => $event->id],
            ['name' => 'Mapa principal', 'width' => 800, 'height' => 600]
        );

        $sectorOffsetX = 40;
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($config['sectors'] as $index => $sectorConfig) {
            $sector = EventSector::query()
                ->where('event_id', $event->id)
                ->where('name', $sectorConfig['name'])
                ->first();

            if (! $sector) {
                continue;
            }

            $rowSeats = $this->resolveRowSeats($sectorConfig);
            $rowCount = count($rowSeats);
            $maxSeatsInRow = $rowCount > 0 ? max($rowSeats) : 10;
            $namingScheme = $sectorConfig['naming_scheme'] ?? 'row_letter';
            $color = $sectorConfig['color'] ?? $this->defaultSectorColor($index);

            $sectorPosY = 120 + ($index * 20);
            $sector->update([
                'color' => $color,
                'category' => $sectorConfig['category'] ?? null,
                'pos_x' => $sectorOffsetX,
                'pos_y' => $sectorPosY - 30,
                'map_visible' => true,
            ]);

            $sectorWidth = $maxSeatsInRow * self::SEAT_SPACING;
            $sectorHeight = $rowCount * self::ROW_SPACING;
            $maxWidth = max($maxWidth, $sectorOffsetX + $sectorWidth + 40);
            $maxHeight = max($maxHeight, $sectorPosY + $sectorHeight + 40);

            $globalSeatIndex = 0;

            foreach ($rowSeats as $rowIndex => $seatsInRow) {
                $rowNum = $rowIndex + 1;
                $rowLabel = $this->buildRowLabel($namingScheme, $rowNum);
                $rowPosY = $sectorPosY + ($rowIndex * self::ROW_SPACING);

                $seatRow = SeatRow::create([
                    'sector_id' => $sector->id,
                    'name' => $rowLabel,
                    'sort_order' => $rowIndex,
                    'pos_x' => $sectorOffsetX,
                    'pos_y' => $rowPosY,
                ]);

                for ($seatNum = 1; $seatNum <= $seatsInRow; $seatNum++) {
                    $globalSeatIndex++;
                    $posX = $sectorOffsetX + (($seatNum - 1) * self::SEAT_SPACING);
                    $seatType = SeatType::STANDARD;
                    $labelData = $this->buildSeatLabel($namingScheme, $rowNum, $rowLabel, $seatNum, $globalSeatIndex);

                    if (! empty($sectorConfig['pcd_seats']) && in_array($labelData['label'], $sectorConfig['pcd_seats'], true)) {
                        $seatType = SeatType::PCD;
                    }

                    Seat::create([
                        'event_id' => $event->id,
                        'sector_id' => $sector->id,
                        'seat_row_id' => $seatRow->id,
                        'row_label' => $labelData['row_label'],
                        'seat_number' => $labelData['seat_number'],
                        'label' => $labelData['label'],
                        'pos_x' => $posX,
                        'pos_y' => $rowPosY,
                        'rotation' => 0,
                        'width' => self::SEAT_SIZE,
                        'height' => self::SEAT_SIZE,
                        'seat_type' => $seatType,
                        'status' => SeatStatus::AVAILABLE,
                    ]);
                }
            }

            $sectorOffsetX += $sectorWidth + self::SECTOR_GAP;
        }

        $venueMap->update([
            'width' => max(800, (int) $maxWidth),
            'height' => max(600, (int) $maxHeight),
            'version' => $venueMap->version + 1,
        ]);

        $this->invalidateCache($event->id);
    }

    public function exportLayoutSnapshot(Event $event): array
    {
        $event->load(['venueMap', 'sectors.seatRows.seats']);

        $venue = $event->venueMap;
        $sectorsConfig = [];

        foreach ($event->sectors->sortBy('sort_order') as $sector) {
            $rows = $sector->seatRows->sortBy('sort_order');
            $rowSeats = $rows->map(fn (SeatRow $row) => $row->seats->count())->values()->all();
            $firstSeat = $sector->seats->first();
            $namingScheme = $this->inferNamingScheme($sector->seats);

            $sectorsConfig[] = [
                'name' => $sector->name,
                'naming_scheme' => $namingScheme,
                'row_seats' => $rowSeats ?: [10],
                'color' => $sector->color,
            ];

            unset($firstSeat);
        }

        $sectorSnapshots = $event->sectors->sortBy('sort_order')->map(function (EventSector $sector) {
            return [
                'name' => $sector->name,
                'color' => $sector->color,
                'category' => $sector->category,
                'pos_x' => (float) $sector->pos_x,
                'pos_y' => (float) $sector->pos_y,
                'rows' => $sector->seatRows->sortBy('sort_order')->map(function (SeatRow $row) {
                    return [
                        'name' => $row->name,
                        'sort_order' => $row->sort_order,
                        'pos_x' => (float) $row->pos_x,
                        'pos_y' => (float) $row->pos_y,
                        'seats' => $row->seats->sortBy('seat_number')->map(fn (Seat $seat) => [
                            'row_label' => $seat->row_label,
                            'seat_number' => $seat->seat_number,
                            'label' => $seat->label,
                            'pos_x' => (float) $seat->pos_x,
                            'pos_y' => (float) $seat->pos_y,
                            'rotation' => (int) $seat->rotation,
                            'width' => (int) $seat->width,
                            'height' => (int) $seat->height,
                            'seat_type' => $seat->seat_type->value,
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'seats_config' => ['sectors' => $sectorsConfig],
            'venue' => $venue ? [
                'width' => $venue->width,
                'height' => $venue->height,
                'stage_label' => $venue->stage_label,
                'stage_x' => $venue->stage_x,
                'stage_y' => $venue->stage_y,
                'stage_width' => $venue->stage_width,
                'stage_height' => $venue->stage_height,
                'floor_plan_url' => $venue->floor_plan_url,
                'floor_plan_opacity' => (float) ($venue->floor_plan_opacity ?? 1),
                'floor_plan_scale_x' => (float) ($venue->floor_plan_scale_x ?? 1),
                'floor_plan_scale_y' => (float) ($venue->floor_plan_scale_y ?? 1),
                'floor_plan_locked' => (bool) ($venue->floor_plan_locked ?? false),
                'floor_plan_visible' => (bool) ($venue->floor_plan_visible ?? true),
                'markers' => $venue->markers ?? [],
                'elements' => $this->formatMapElements($venue),
            ] : null,
            'sectors' => $sectorSnapshots,
        ];
    }

    private function resolveRowSeats(array $sectorConfig): array
    {
        if (! empty($sectorConfig['row_seats']) && is_array($sectorConfig['row_seats'])) {
            return array_map('intval', $sectorConfig['row_seats']);
        }

        $rows = (int) ($sectorConfig['rows'] ?? 5);
        $seatsPerRow = (int) ($sectorConfig['seats_per_row'] ?? 10);

        return array_fill(0, $rows, $seatsPerRow);
    }

    private function buildRowLabel(string $scheme, int $rowNum): string
    {
        return match ($scheme) {
            'numeric_sequential', 'numeric_row_prefix' => (string) $rowNum,
            default => $this->rowLetter($rowNum),
        };
    }

    private function buildSeatLabel(
        string $scheme,
        int $rowNum,
        string $rowLabel,
        int $seatNum,
        int $globalSeatIndex,
    ): array {
        $label = match ($scheme) {
            'numeric_sequential' => (string) $globalSeatIndex,
            'numeric_row_prefix' => $rowNum.$seatNum,
            default => $rowLabel.$seatNum,
        };

        return [
            'row_label' => $rowLabel,
            'seat_number' => (string) $seatNum,
            'label' => $label,
        ];
    }

    private function rowLetter(int $rowNum): string
    {
        $label = '';
        $n = $rowNum;

        while ($n > 0) {
            $n--;
            $label = chr(65 + ($n % 26)).$label;
            $n = intdiv($n, 26);
        }

        return $label ?: 'A';
    }

    private function inferNamingScheme($seats): string
    {
        $sample = $seats->take(3);

        if ($sample->isEmpty()) {
            return 'row_letter';
        }

        $first = $sample->first();

        if (preg_match('/^[A-Z]+\d+$/', $first->label)) {
            return 'row_letter';
        }

        if (preg_match('/^\d+$/', $first->label)) {
            return 'numeric_sequential';
        }

        if (preg_match('/^\d{2,}$/', $first->label)) {
            return 'numeric_row_prefix';
        }

        return 'row_letter';
    }

    public function getPublicMapBySlug(string $slug, ?array $bbox = null): array
    {
        $event = Event::query()->where('slug', $slug)->firstOrFail();

        if (! $event->has_seats) {
            abort(404, 'Evento não possui mapa de assentos.');
        }

        return $this->getMapForEvent($event, $bbox);
    }

    public function getMapForEvent(Event $event, ?array $bbox = null): array
    {
        if ($bbox !== null) {
            return $this->buildMapPayload($event, includeHoldInfo: true, bbox: $bbox);
        }

        $cacheKey = $this->cacheKey($event->id);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($event) {
            return $this->buildMapPayload($event);
        });
    }

    public function getAdminMap(int $eventId, ?array $bbox = null): array
    {
        $event = Event::query()->findOrFail($eventId);

        return $this->buildMapPayload($event, includeHoldInfo: false, bbox: $bbox);
    }

    public function uploadFloorPlan(int $eventId, UploadedFile $file): array
    {
        $event = Event::query()->findOrFail($eventId);

        $venueMap = VenueMap::query()->firstOrCreate(
            ['event_id' => $event->id],
            ['name' => 'Mapa principal', 'width' => 800, 'height' => 600]
        );

        $upload = app(CloudinaryService::class)->uploadFloorPlan($file);

        $venueMap->update([
            'floor_plan_url' => $upload['url'],
            'version' => $venueMap->version + 1,
        ]);

        $this->invalidateCache($event->id);

        return $this->buildMapPayload($event->fresh(), includeHoldInfo: false);
    }

    public function saveLayout(int $eventId, array $data): array
    {
        return DB::transaction(function () use ($eventId, $data) {
            $event = Event::query()->lockForUpdate()->findOrFail($eventId);

            $venueMap = VenueMap::query()->firstOrCreate(
                ['event_id' => $event->id],
                ['name' => 'Mapa principal']
            );

            if (isset($data['venue_map'])) {
                $venueMap->update([
                    'name' => $data['venue_map']['name'] ?? $venueMap->name,
                    'floor_plan_url' => $data['venue_map']['floor_plan_url'] ?? $venueMap->floor_plan_url,
                    'floor_plan_opacity' => $data['venue_map']['floor_plan_opacity'] ?? $venueMap->floor_plan_opacity ?? 1,
                    'floor_plan_scale_x' => $data['venue_map']['floor_plan_scale_x'] ?? $venueMap->floor_plan_scale_x ?? 1,
                    'floor_plan_scale_y' => $data['venue_map']['floor_plan_scale_y'] ?? $venueMap->floor_plan_scale_y ?? 1,
                    'floor_plan_locked' => $data['venue_map']['floor_plan_locked'] ?? $venueMap->floor_plan_locked ?? false,
                    'floor_plan_visible' => array_key_exists('floor_plan_visible', $data['venue_map'])
                        ? (bool) $data['venue_map']['floor_plan_visible']
                        : ($venueMap->floor_plan_visible ?? true),
                    'width' => (int) ($data['venue_map']['width'] ?? $venueMap->width),
                    'height' => (int) ($data['venue_map']['height'] ?? $venueMap->height),
                    'stage_label' => $data['venue_map']['stage_label'] ?? $venueMap->stage_label,
                    'stage_x' => array_key_exists('stage_x', $data['venue_map'])
                        ? ($data['venue_map']['stage_x'] !== null ? (int) $data['venue_map']['stage_x'] : null)
                        : $venueMap->stage_x,
                    'stage_y' => array_key_exists('stage_y', $data['venue_map'])
                        ? ($data['venue_map']['stage_y'] !== null ? (int) $data['venue_map']['stage_y'] : null)
                        : $venueMap->stage_y,
                    'stage_width' => (int) ($data['venue_map']['stage_width'] ?? $venueMap->stage_width ?? 280),
                    'stage_height' => (int) ($data['venue_map']['stage_height'] ?? $venueMap->stage_height ?? 36),
                    'markers' => array_key_exists('markers', $data['venue_map'])
                        ? $this->normalizeMarkers($data['venue_map']['markers'] ?? [])
                        : $venueMap->markers,
                    'version' => $venueMap->version + 1,
                ]);

                if (array_key_exists('elements', $data['venue_map'])) {
                    $this->syncElements($venueMap, $data['venue_map']['elements'] ?? []);
                }
            }

            foreach ($data['sectors'] ?? [] as $sectorData) {
                $sector = EventSector::query()
                    ->where('event_id', $event->id)
                    ->where('id', $sectorData['id'])
                    ->first();

                if (! $sector) {
                    continue;
                }

                $sector->update([
                    'name' => $sectorData['name'] ?? $sector->name,
                    'color' => $sectorData['color'] ?? $sector->color,
                    'category' => $sectorData['category'] ?? $sector->category,
                    'pos_x' => $sectorData['pos_x'] ?? $sector->pos_x,
                    'pos_y' => $sectorData['pos_y'] ?? $sector->pos_y,
                    'map_visible' => $sectorData['map_visible'] ?? $sector->map_visible,
                ]);
            }

            foreach ($data['seats'] ?? [] as $seatData) {
                $seat = Seat::query()
                    ->where('event_id', $event->id)
                    ->where('id', $seatData['id'])
                    ->first();

                if (! $seat) {
                    continue;
                }

                $updates = array_filter([
                    'pos_x' => $seatData['pos_x'] ?? null,
                    'pos_y' => $seatData['pos_y'] ?? null,
                    'rotation' => $seatData['rotation'] ?? null,
                    'width' => $seatData['width'] ?? null,
                    'height' => $seatData['height'] ?? null,
                    'seat_type' => $seatData['seat_type'] ?? null,
                    'label' => $seatData['label'] ?? null,
                ], fn ($v) => $v !== null);

                if (isset($seatData['seat_type']) && in_array($seatData['seat_type'], self::ALLOWED_SEAT_TYPES, true)) {
                    $updates['seat_type'] = $seatData['seat_type'];
                }

                if ($updates !== []) {
                    $seat->update($updates);
                }
            }

            $this->invalidateCache($event->id);

            return $this->buildMapPayload($event->fresh(), includeHoldInfo: false);
        });
    }

    public function invalidateCache(int $eventId): void
    {
        Cache::forget($this->cacheKey($eventId));
    }

    private function buildMapPayload(Event $event, bool $includeHoldInfo = true, ?array $bbox = null): array
    {
        $event->load([
            'venueMap.mapElements',
            'sectors.tickets',
            'sectors.seatRows.seats',
        ]);

        $venueMap = $event->venueMap ?? VenueMap::query()->firstOrCreate(
            ['event_id' => $event->id],
            ['name' => 'Mapa principal', 'width' => 800, 'height' => 600]
        );

        $heldSeatIds = $includeHoldInfo ? $this->seatHoldService->getHeldSeatIdsForEvent($event->id) : [];

        $sectors = $event->sectors
            ->sortBy('sort_order')
            ->filter(fn (EventSector $s) => $s->map_visible)
            ->map(function (EventSector $sector) use ($heldSeatIds) {
                $ticket = $sector->tickets->first();
                $rows = $sector->seatRows->sortBy('sort_order')->map(function (SeatRow $row) use ($sector, $heldSeatIds) {
                    $seats = $row->seats->sortBy('seat_number')->map(fn (Seat $seat) => $this->formatSeat($seat, $heldSeatIds));

                    return [
                        'id' => $row->id,
                        'name' => $row->name,
                        'sort_order' => $row->sort_order,
                        'pos_x' => (float) $row->pos_x,
                        'pos_y' => (float) $row->pos_y,
                        'seats' => $seats->values(),
                    ];
                });

                return [
                    'id' => $sector->id,
                    'name' => $sector->name,
                    'color' => $sector->color,
                    'category' => $sector->category,
                    'pos_x' => (float) $sector->pos_x,
                    'pos_y' => (float) $sector->pos_y,
                    'price' => $ticket ? (int) $ticket->price : 0,
                    'ticket_id' => $ticket?->id,
                    'rows' => $rows->values(),
                ];
            });

        $allSeats = Seat::query()
            ->where('event_id', $event->id)
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get()
            ->map(fn (Seat $seat) => $this->formatSeat($seat, $heldSeatIds));

        $allSeats = Seat::query()
            ->where('event_id', $event->id)
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get()
            ->map(fn (Seat $seat) => $this->formatSeat($seat, $heldSeatIds));

        if ($bbox !== null) {
            $allSeats = $allSeats->filter(fn (array $seat) => $this->seatInBbox($seat, $bbox))->values();
        }

        $totalSeats = Seat::query()->where('event_id', $event->id)->count();

        return [
            'event_id' => $event->id,
            'event_slug' => $event->slug,
            'total_seats' => $totalSeats,
            'bbox_filtered' => $bbox !== null,
            'venue_map' => [
                'id' => $venueMap->id,
                'name' => $venueMap->name,
                'floor_plan_url' => $venueMap->floor_plan_url,
                'floor_plan_opacity' => (float) ($venueMap->floor_plan_opacity ?? 1),
                'floor_plan_scale_x' => (float) ($venueMap->floor_plan_scale_x ?? 1),
                'floor_plan_scale_y' => (float) ($venueMap->floor_plan_scale_y ?? 1),
                'floor_plan_locked' => (bool) ($venueMap->floor_plan_locked ?? false),
                'floor_plan_visible' => (bool) ($venueMap->floor_plan_visible ?? true),
                'width' => $venueMap->width,
                'height' => $venueMap->height,
                'stage_label' => $venueMap->stage_label,
                'stage_x' => $venueMap->stage_x ?? max(0, (int) (($venueMap->width - ($venueMap->stage_width ?? 280)) / 2)),
                'stage_y' => $venueMap->stage_y ?? 20,
                'stage_width' => $venueMap->stage_width ?? 280,
                'stage_height' => $venueMap->stage_height ?? 36,
                'markers' => $this->normalizeMarkers($venueMap->markers ?? []),
                'elements' => $this->formatMapElements($venueMap),
                'version' => $venueMap->version,
            ],
            'sectors' => $sectors->values(),
            'seats' => $allSeats->values(),
        ];
    }

    public function generateRow(int $eventId, array $config): array
    {
        return DB::transaction(function () use ($eventId, $config) {
            $event = Event::query()->findOrFail($eventId);
            $sector = EventSector::query()
                ->where('event_id', $event->id)
                ->where('id', $config['sector_id'])
                ->firstOrFail();

            $positions = $this->rowGenerationService->generatePositions($config);
            $rowLabel = (string) ($config['row_label'] ?? 'A');
            $sortOrder = (int) ($config['sort_order'] ?? $sector->seatRows()->count());

            $seatRow = isset($config['seat_row_id'])
                ? SeatRow::query()->where('sector_id', $sector->id)->findOrFail($config['seat_row_id'])
                : SeatRow::create([
                    'sector_id' => $sector->id,
                    'name' => $rowLabel,
                    'sort_order' => $sortOrder,
                    'pos_x' => $positions[0]['pos_x'] ?? 0,
                    'pos_y' => $positions[0]['pos_y'] ?? 0,
                ]);

            $created = [];
            foreach ($positions as $position) {
                $created[] = Seat::create([
                    'event_id' => $event->id,
                    'sector_id' => $sector->id,
                    'seat_row_id' => $seatRow->id,
                    'row_label' => $position['row_label'],
                    'seat_number' => $position['seat_number'],
                    'label' => $position['label'],
                    'pos_x' => $position['pos_x'],
                    'pos_y' => $position['pos_y'],
                    'rotation' => (int) ($position['rotation'] ?? 0),
                    'width' => self::SEAT_SIZE,
                    'height' => self::SEAT_SIZE,
                    'seat_type' => SeatType::from($config['seat_type'] ?? 'standard'),
                    'status' => SeatStatus::AVAILABLE,
                ]);
            }

            $this->invalidateCache($event->id);

            return [
                'seat_row_id' => $seatRow->id,
                'seats' => collect($created)->map(fn (Seat $seat) => $this->formatSeat($seat, []))->values()->all(),
            ];
        });
    }

    public function createSeats(int $eventId, array $seatsData): array
    {
        return DB::transaction(function () use ($eventId, $seatsData) {
            $event = Event::query()->findOrFail($eventId);
            $created = [];

            foreach ($seatsData as $seatData) {
                $sector = EventSector::query()
                    ->where('event_id', $event->id)
                    ->where('id', $seatData['sector_id'])
                    ->firstOrFail();

                $seatRowId = $seatData['seat_row_id'] ?? null;
                if ($seatRowId) {
                    SeatRow::query()->where('sector_id', $sector->id)->findOrFail($seatRowId);
                }

                $seatType = in_array($seatData['seat_type'] ?? 'standard', self::ALLOWED_SEAT_TYPES, true)
                    ? $seatData['seat_type']
                    : 'standard';

                $created[] = Seat::create([
                    'event_id' => $event->id,
                    'sector_id' => $sector->id,
                    'seat_row_id' => $seatRowId,
                    'row_label' => $seatData['row_label'] ?? 'A',
                    'seat_number' => (string) ($seatData['seat_number'] ?? '1'),
                    'label' => $seatData['label'] ?? (($seatData['row_label'] ?? 'A').($seatData['seat_number'] ?? '1')),
                    'pos_x' => (float) ($seatData['pos_x'] ?? 0),
                    'pos_y' => (float) ($seatData['pos_y'] ?? 0),
                    'rotation' => (int) ($seatData['rotation'] ?? 0),
                    'width' => (int) ($seatData['width'] ?? self::SEAT_SIZE),
                    'height' => (int) ($seatData['height'] ?? self::SEAT_SIZE),
                    'seat_type' => SeatType::from($seatType),
                    'status' => SeatStatus::AVAILABLE,
                ]);
            }

            $this->invalidateCache($event->id);

            return collect($created)->map(fn (Seat $seat) => $this->formatSeat($seat, []))->values()->all();
        });
    }

    public function deleteSeats(int $eventId, array $seatIds): void
    {
        DB::transaction(function () use ($eventId, $seatIds) {
            Seat::query()
                ->where('event_id', $eventId)
                ->whereIn('id', $seatIds)
                ->where('status', SeatStatus::AVAILABLE)
                ->delete();

            $this->invalidateCache($eventId);
        });
    }

    public function syncElementsFromSnapshot(VenueMap $venueMap, array $elements): void
    {
        $this->syncElements($venueMap, $elements);
    }

    private function syncElements(VenueMap $venueMap, array $elements): void
    {
        $normalized = $this->normalizeElements($elements);
        $keys = collect($normalized)->pluck('element_key')->all();

        MapElement::query()
            ->where('venue_map_id', $venueMap->id)
            ->whereNotIn('element_key', $keys)
            ->delete();

        foreach ($normalized as $index => $element) {
            MapElement::query()->updateOrCreate(
                [
                    'venue_map_id' => $venueMap->id,
                    'element_key' => $element['element_key'],
                ],
                [
                    'type' => $element['type'],
                    'label' => $element['label'],
                    'pos_x' => $element['pos_x'],
                    'pos_y' => $element['pos_y'],
                    'rotation' => $element['rotation'],
                    'scale' => $element['scale'],
                    'width' => $element['width'],
                    'height' => $element['height'],
                    'props' => $element['props'],
                    'sort_order' => $index,
                ],
            );
        }
    }

    private function normalizeElements(array $elements): array
    {
        $allowedTypes = ['bathroom', 'exit', 'entrance', 'bar', 'stairs', 'elevator', 'info', 'stage', 'label'];

        return collect($elements)
            ->filter(fn ($el) => is_array($el) && isset($el['type']))
            ->map(function (array $el) use ($allowedTypes) {
                $type = in_array($el['type'], $allowedTypes, true) ? $el['type'] : 'info';

                return [
                    'element_key' => (string) ($el['id'] ?? $el['element_key'] ?? uniqid('el-', true)),
                    'type' => $type,
                    'label' => isset($el['label']) ? (string) $el['label'] : '',
                    'pos_x' => (float) ($el['pos_x'] ?? 0),
                    'pos_y' => (float) ($el['pos_y'] ?? 0),
                    'rotation' => (int) ($el['rotation'] ?? 0),
                    'scale' => (float) ($el['scale'] ?? 1),
                    'width' => (int) ($el['width'] ?? 56),
                    'height' => (int) ($el['height'] ?? 56),
                    'props' => is_array($el['props'] ?? null) ? $el['props'] : [],
                ];
            })
            ->values()
            ->all();
    }

    private function formatMapElements(VenueMap $venueMap): array
    {
        $venueMap->loadMissing('mapElements');

        return $venueMap->mapElements
            ->sortBy('sort_order')
            ->map(fn (MapElement $el) => [
                'id' => $el->element_key,
                'type' => $el->type,
                'label' => $el->label ?? '',
                'pos_x' => (float) $el->pos_x,
                'pos_y' => (float) $el->pos_y,
                'rotation' => (int) $el->rotation,
                'scale' => (float) $el->scale,
                'width' => (int) $el->width,
                'height' => (int) $el->height,
                'props' => $el->props ?? [],
            ])
            ->values()
            ->all();
    }

    private function seatInBbox(array $seat, array $bbox): bool
    {
        $padding = (float) ($bbox['padding'] ?? 40);
        $minX = (float) $bbox['min_x'] - $padding;
        $minY = (float) $bbox['min_y'] - $padding;
        $maxX = (float) $bbox['max_x'] + $padding;
        $maxY = (float) $bbox['max_y'] + $padding;

        $x = (float) $seat['pos_x'];
        $y = (float) $seat['pos_y'];
        $w = (float) ($seat['width'] ?? self::SEAT_SIZE);
        $h = (float) ($seat['height'] ?? self::SEAT_SIZE);

        return $x + $w >= $minX && $x <= $maxX && $y + $h >= $minY && $y <= $maxY;
    }

    private function normalizeMarkers(array $markers): array
    {
        $allowedTypes = ['bathroom', 'exit', 'entrance', 'bar', 'stairs', 'elevator', 'info'];

        return collect($markers)
            ->filter(fn ($marker) => is_array($marker) && isset($marker['type']) && in_array($marker['type'], $allowedTypes, true))
            ->map(function (array $marker) {
                return [
                    'id' => (string) ($marker['id'] ?? uniqid('marker-', true)),
                    'type' => (string) $marker['type'],
                    'label' => isset($marker['label']) ? (string) $marker['label'] : '',
                    'pos_x' => (float) ($marker['pos_x'] ?? 0),
                    'pos_y' => (float) ($marker['pos_y'] ?? 0),
                    'width' => (int) ($marker['width'] ?? 56),
                    'height' => (int) ($marker['height'] ?? 56),
                ];
            })
            ->values()
            ->all();
    }

    private function formatSeat(Seat $seat, array $heldSeatIds): array
    {
        $effectiveStatus = $seat->status->value;

        if ($seat->status === SeatStatus::AVAILABLE && in_array($seat->id, $heldSeatIds, true)) {
            $effectiveStatus = 'held';
        }

        if ($seat->seat_type === SeatType::BLOCKED) {
            $effectiveStatus = 'blocked';
        }

        return [
            'id' => $seat->id,
            'sector_id' => $seat->sector_id,
            'seat_row_id' => $seat->seat_row_id,
            'label' => $seat->label,
            'row_label' => $seat->row_label,
            'seat_number' => $seat->seat_number,
            'pos_x' => (float) $seat->pos_x,
            'pos_y' => (float) $seat->pos_y,
            'rotation' => (int) $seat->rotation,
            'width' => (int) $seat->width,
            'height' => (int) $seat->height,
            'seat_type' => $seat->seat_type->value,
            'status' => $effectiveStatus,
        ];
    }

    private function defaultSectorColor(int $index): string
    {
        $colors = ['#003366', '#1a5276', '#2874a6', '#5dade2', '#85929e'];

        return $colors[$index % count($colors)];
    }

    private function cacheKey(int $eventId): string
    {
        return "event:{$eventId}:seat-map";
    }
}

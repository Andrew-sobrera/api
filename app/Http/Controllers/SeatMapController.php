<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\SeatHoldService;
use App\Services\SeatMapService;
use Illuminate\Http\Request;

class SeatMapController extends Controller
{
    public function __construct(
        protected SeatMapService $seatMapService,
        protected SeatHoldService $seatHoldService,
    ) {}

    public function publicShow(Request $request, string $slug)
    {
        return response()->json(
            $this->seatMapService->getPublicMapBySlug($slug, $this->parseBbox($request))
        );
    }

    public function show(Request $request, int $eventId)
    {
        Event::query()->findOrFail($eventId);

        return response()->json(
            $this->seatMapService->getAdminMap($eventId, $this->parseBbox($request))
        );
    }

    public function update(Request $request, int $eventId)
    {
        $validated = $request->validate([
            'venue_map' => ['sometimes', 'array'],
            'venue_map.name' => ['sometimes', 'string', 'max:255'],
            'venue_map.floor_plan_url' => ['nullable', 'string', 'url'],
            'venue_map.floor_plan_opacity' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'venue_map.floor_plan_scale_x' => ['sometimes', 'numeric', 'min:0.1', 'max:5'],
            'venue_map.floor_plan_scale_y' => ['sometimes', 'numeric', 'min:0.1', 'max:5'],
            'venue_map.floor_plan_locked' => ['sometimes', 'boolean'],
            'venue_map.floor_plan_visible' => ['sometimes', 'boolean'],
            'venue_map.width' => ['sometimes', 'integer', 'min:100'],
            'venue_map.height' => ['sometimes', 'integer', 'min:100'],
            'venue_map.stage_label' => ['sometimes', 'string', 'max:50'],
            'venue_map.stage_x' => ['nullable', 'integer', 'min:0'],
            'venue_map.stage_y' => ['nullable', 'integer', 'min:0'],
            'venue_map.stage_width' => ['sometimes', 'integer', 'min:80', 'max:800'],
            'venue_map.stage_height' => ['sometimes', 'integer', 'min:24', 'max:200'],
            'venue_map.markers' => ['sometimes', 'array'],
            'venue_map.markers.*.id' => ['required', 'string', 'max:64'],
            'venue_map.markers.*.type' => ['required', 'string', 'in:bathroom,exit,entrance,bar,stairs,elevator,info'],
            'venue_map.markers.*.label' => ['nullable', 'string', 'max:50'],
            'venue_map.markers.*.pos_x' => ['required', 'numeric'],
            'venue_map.markers.*.pos_y' => ['required', 'numeric'],
            'venue_map.markers.*.width' => ['sometimes', 'integer', 'min:24', 'max:200'],
            'venue_map.markers.*.height' => ['sometimes', 'integer', 'min:24', 'max:200'],
            'venue_map.elements' => ['sometimes', 'array'],
            'venue_map.elements.*.id' => ['required', 'string', 'max:64'],
            'venue_map.elements.*.type' => ['required', 'string', 'max:32'],
            'venue_map.elements.*.label' => ['nullable', 'string', 'max:50'],
            'venue_map.elements.*.pos_x' => ['required', 'numeric'],
            'venue_map.elements.*.pos_y' => ['required', 'numeric'],
            'sectors' => ['sometimes', 'array'],
            'sectors.*.id' => ['required', 'integer'],
            'sectors.*.name' => ['sometimes', 'string', 'max:255'],
            'sectors.*.color' => ['sometimes', 'string', 'max:7'],
            'sectors.*.category' => ['nullable', 'string', 'max:100'],
            'sectors.*.pos_x' => ['sometimes', 'numeric'],
            'sectors.*.pos_y' => ['sometimes', 'numeric'],
            'sectors.*.map_visible' => ['sometimes', 'boolean'],
            'seats' => ['sometimes', 'array'],
            'seats.*.id' => ['required', 'integer'],
            'seats.*.pos_x' => ['sometimes', 'numeric'],
            'seats.*.pos_y' => ['sometimes', 'numeric'],
            'seats.*.rotation' => ['sometimes', 'integer', 'min:0', 'max:360'],
            'seats.*.width' => ['sometimes', 'integer', 'min:16', 'max:64'],
            'seats.*.height' => ['sometimes', 'integer', 'min:16', 'max:64'],
            'seats.*.seat_type' => ['sometimes', 'string', 'in:standard,pcd,companion,blocked,vip,table,booth'],
            'seats.*.label' => ['sometimes', 'string', 'max:20'],
        ]);

        $map = $this->seatMapService->saveLayout($eventId, $validated);

        return response()->json($map);
    }

    public function generateRow(Request $request, int $eventId)
    {
        $validated = $request->validate([
            'sector_id' => ['required', 'integer'],
            'seat_row_id' => ['nullable', 'integer'],
            'count' => ['required', 'integer', 'min:1', 'max:500'],
            'spacing' => ['sometimes', 'numeric', 'min:4', 'max:120'],
            'start_x' => ['sometimes', 'numeric'],
            'start_y' => ['sometimes', 'numeric'],
            'rotation' => ['sometimes', 'numeric', 'min:0', 'max:360'],
            'curvature' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'direction' => ['sometimes', 'string', 'in:horizontal,vertical'],
            'row_label' => ['sometimes', 'string', 'max:10'],
            'naming_scheme' => ['sometimes', 'string', 'in:row_letter,numeric_sequential,numeric_row_prefix'],
            'global_start' => ['sometimes', 'integer', 'min:1'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'seat_type' => ['sometimes', 'string', 'in:standard,pcd,companion,blocked,vip,table,booth'],
        ]);

        return response()->json($this->seatMapService->generateRow($eventId, $validated));
    }

    public function createSeats(Request $request, int $eventId)
    {
        $validated = $request->validate([
            'seats' => ['required', 'array', 'min:1', 'max:100'],
            'seats.*.sector_id' => ['required', 'integer'],
            'seats.*.seat_row_id' => ['nullable', 'integer'],
            'seats.*.row_label' => ['sometimes', 'string', 'max:10'],
            'seats.*.seat_number' => ['sometimes', 'string', 'max:10'],
            'seats.*.label' => ['sometimes', 'string', 'max:20'],
            'seats.*.pos_x' => ['required', 'numeric'],
            'seats.*.pos_y' => ['required', 'numeric'],
            'seats.*.rotation' => ['sometimes', 'integer', 'min:0', 'max:360'],
            'seats.*.width' => ['sometimes', 'integer', 'min:16', 'max:64'],
            'seats.*.height' => ['sometimes', 'integer', 'min:16', 'max:64'],
            'seats.*.seat_type' => ['sometimes', 'string', 'in:standard,pcd,companion,blocked,vip,table,booth'],
        ]);

        return response()->json([
            'seats' => $this->seatMapService->createSeats($eventId, $validated['seats']),
        ], 201);
    }

    public function deleteSeats(Request $request, int $eventId)
    {
        $seatIds = $request->input('seat_ids', $request->query('seat_ids', []));

        $validated = validator(
            ['seat_ids' => is_array($seatIds) ? $seatIds : explode(',', (string) $seatIds)],
            ['seat_ids' => ['required', 'array', 'min:1'], 'seat_ids.*' => ['integer']],
        )->validate();

        $this->seatMapService->deleteSeats($eventId, $validated['seat_ids']);

        return response()->json(['message' => 'Assentos removidos.']);
    }

    public function uploadFloorPlan(Request $request, int $eventId)
    {
        $request->validate([
            'floor_plan' => ['required', 'image', 'max:20480'],
        ]);

        return response()->json(
            $this->seatMapService->uploadFloorPlan($eventId, $request->file('floor_plan'))
        );
    }

    public function hold(Request $request)
    {
        $validated = $request->validate([
            'seat_ids' => ['required', 'array', 'min:1', 'max:20'],
            'seat_ids.*' => ['integer'],
            'session_id' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $this->seatHoldService->holdSeats(
            $validated['seat_ids'],
            $request->user()?->id,
            $validated['session_id'] ?? $request->header('X-Session-Id'),
        );

        if ($result['held'] === [] && $result['failed'] !== []) {
            return response()->json([
                'message' => 'Nenhum assento disponível.',
                ...$result,
            ], 409);
        }

        return response()->json($result);
    }

    public function releaseHolds(Request $request)
    {
        $validated = $request->validate([
            'seat_ids' => ['nullable', 'array'],
            'seat_ids.*' => ['integer'],
            'session_id' => ['nullable', 'string', 'max:64'],
        ]);

        $this->seatHoldService->releaseHolds(
            $request->user()?->id,
            $validated['session_id'] ?? $request->header('X-Session-Id'),
            $validated['seat_ids'] ?? null,
        );

        return response()->json(['message' => 'Reservas temporárias liberadas.']);
    }

    private function parseBbox(Request $request): ?array
    {
        if (! $request->has(['min_x', 'min_y', 'max_x', 'max_y'])) {
            return null;
        }

        return [
            'min_x' => (float) $request->query('min_x'),
            'min_y' => (float) $request->query('min_y'),
            'max_x' => (float) $request->query('max_x'),
            'max_y' => (float) $request->query('max_y'),
            'padding' => $request->has('padding') ? (float) $request->query('padding') : 40,
        ];
    }
}

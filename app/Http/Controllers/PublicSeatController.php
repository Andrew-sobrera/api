<?php

namespace App\Http\Controllers;

use App\Http\Resources\SeatResource;
use App\Models\EventSector;
use App\Services\SeatService;

class PublicSeatController extends Controller
{
    public function __construct(protected SeatService $seatService)
    {
    }

    public function index(int $sectorId)
    {
        $sector = EventSector::query()
            ->with('event')
            ->findOrFail($sectorId);

        if ($sector->event->status !== 'active') {
            return response()->json(['message' => 'Setor indisponível.'], 403);
        }

        $seats = $this->seatService->getSeatsForSectorMap($sectorId);

        return SeatResource::collection($seats);
    }
}

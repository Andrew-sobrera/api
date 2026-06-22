<?php

namespace App\Http\Controllers;

use App\Http\Resources\SeatResource;
use App\Services\SeatService;
use Illuminate\Http\Request;

class SeatController extends Controller
{
    public function __construct(protected SeatService $seatService)
    {
    }

    public function index(Request $request, int $sectorId)
    {
        $seats = $this->seatService->getAvailableSeatsForSector($sectorId);

        return SeatResource::collection($seats);
    }
}

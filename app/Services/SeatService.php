<?php

namespace App\Services;

use App\Enums\SeatStatus;
use App\Models\Event;
use App\Models\EventSector;
use App\Models\Seat;

class SeatService
{
    public function generateFromConfig(Event $event, array $config): void
    {
        foreach ($config['sectors'] as $sectorConfig) {
            $sector = EventSector::query()
                ->where('event_id', $event->id)
                ->where('name', $sectorConfig['name'])
                ->first();

            if (! $sector) {
                continue;
            }

            $rows = (int) ($sectorConfig['rows'] ?? 0);
            $seatsPerRow = (int) ($sectorConfig['seats_per_row'] ?? 0);

            for ($row = 1; $row <= $rows; $row++) {
                $rowLabel = chr(64 + $row);

                for ($seat = 1; $seat <= $seatsPerRow; $seat++) {
                    Seat::create([
                        'event_id' => $event->id,
                        'sector_id' => $sector->id,
                        'row_label' => $rowLabel,
                        'seat_number' => (string) $seat,
                        'label' => $rowLabel.$seat,
                        'status' => SeatStatus::AVAILABLE,
                    ]);
                }
            }
        }
    }

    public function reserveSeat(int $seatId): Seat
    {
        $seat = Seat::query()->lockForUpdate()->findOrFail($seatId);

        if ($seat->status !== SeatStatus::AVAILABLE) {
            throw new \App\Exceptions\InsufficientStockException('Assento indisponível.');
        }

        $seat->update(['status' => SeatStatus::RESERVED]);
        app(SeatMapService::class)->invalidateCache($seat->event_id);
        app(SeatHoldService::class)->invalidateEventHoldCache($seat->event_id);

        return $seat->fresh();
    }

    public function releaseSeat(int $seatId): Seat
    {
        $seat = Seat::findOrFail($seatId);
        $seat->update(['status' => SeatStatus::AVAILABLE]);
        app(SeatMapService::class)->invalidateCache($seat->event_id);
        app(SeatHoldService::class)->invalidateEventHoldCache($seat->event_id);

        return $seat->fresh();
    }

    public function confirmSeat(int $seatId): Seat
    {
        $seat = Seat::findOrFail($seatId);
        $seat->update(['status' => SeatStatus::SOLD]);
        app(SeatMapService::class)->invalidateCache($seat->event_id);
        app(SeatHoldService::class)->confirmHolds([$seatId]);
        app(SeatHoldService::class)->invalidateEventHoldCache($seat->event_id);

        return $seat->fresh();
    }

    public function getAvailableSeatsForSector(int $sectorId)
    {
        return Seat::query()
            ->where('sector_id', $sectorId)
            ->where('status', SeatStatus::AVAILABLE)
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get();
    }

    public function getSeatsForSectorMap(int $sectorId)
    {
        return Seat::query()
            ->where('sector_id', $sectorId)
            ->orderBy('row_label')
            ->orderBy('seat_number')
            ->get(['id', 'sector_id', 'row_label', 'seat_number', 'label', 'status']);
    }
}

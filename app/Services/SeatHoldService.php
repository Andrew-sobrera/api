<?php

namespace App\Services;

use App\Enums\SeatStatus;
use App\Models\Seat;
use Illuminate\Support\Facades\Cache;

class SeatHoldService
{
    public function holdSeats(array $seatIds, ?int $userId = null, ?string $sessionId = null): array
    {
        $ttlMinutes = (int) config('checkout.seat_hold_ttl_minutes', 10);
        $ttlSeconds = $ttlMinutes * 60;
        $holderKey = $this->holderKey($userId, $sessionId);
        $held = [];
        $failed = [];

        foreach ($seatIds as $seatId) {
            $seat = Seat::query()->find($seatId);

            if (! $seat || $seat->status !== SeatStatus::AVAILABLE || $seat->seat_type->value === 'blocked') {
                $failed[] = ['seat_id' => $seatId, 'reason' => 'unavailable'];

                continue;
            }

            $lockKey = $this->seatLockKey($seatId);
            $lock = Cache::lock($lockKey, $ttlSeconds);

            if (! $lock->get()) {
                $failed[] = ['seat_id' => $seatId, 'reason' => 'held_by_other'];

                continue;
            }

            Cache::put("{$lockKey}:holder", $holderKey, $ttlSeconds);

            $holderSeats = Cache::get($this->holderSeatsKey($holderKey), []);
            $holderSeats[] = (int) $seatId;
            Cache::put($this->holderSeatsKey($holderKey), array_values(array_unique($holderSeats)), $ttlSeconds);

            $this->invalidateEventHoldCache($seat->event_id);

            $held[] = [
                'seat_id' => (int) $seatId,
                'label' => $seat->label,
                'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String(),
            ];
        }

        return [
            'held' => $held,
            'failed' => $failed,
            'ttl_minutes' => $ttlMinutes,
        ];
    }

    public function releaseHolds(?int $userId = null, ?string $sessionId = null, ?array $seatIds = null): void
    {
        $holderKey = $this->holderKey($userId, $sessionId);
        $storedIds = $seatIds ?? Cache::get($this->holderSeatsKey($holderKey), []);

        foreach ($storedIds as $seatId) {
            $lockKey = $this->seatLockKey((int) $seatId);
            $currentHolder = Cache::get("{$lockKey}:holder");

            if ($currentHolder === $holderKey) {
                Cache::forget("{$lockKey}:holder");
                Cache::lock($lockKey)->forceRelease();
            }
        }

        if ($seatIds === null) {
            Cache::forget($this->holderSeatsKey($holderKey));
        } else {
            $remaining = array_values(array_diff(
                Cache::get($this->holderSeatsKey($holderKey), []),
                array_map('intval', $seatIds),
            ));
            if ($remaining === []) {
                Cache::forget($this->holderSeatsKey($holderKey));
            } else {
                Cache::put($this->holderSeatsKey($holderKey), $remaining, now()->addMinutes(10));
            }
        }
    }

    public function confirmHolds(array $seatIds): void
    {
        foreach ($seatIds as $seatId) {
            $lockKey = $this->seatLockKey((int) $seatId);
            Cache::forget("{$lockKey}:holder");
            Cache::lock($lockKey)->forceRelease();
        }
    }

    public function getHeldSeatIdsForEvent(int $eventId): array
    {
        $cacheKey = "event:{$eventId}:held-seats";

        return Cache::remember($cacheKey, now()->addSeconds(5), function () use ($eventId) {
            $seatIds = Seat::query()
                ->where('event_id', $eventId)
                ->pluck('id')
                ->all();

            $held = [];

            foreach ($seatIds as $seatId) {
                if (Cache::has("{$this->seatLockKey($seatId)}:holder")) {
                    $held[] = (int) $seatId;
                }
            }

            return $held;
        });
    }

    public function invalidateEventHoldCache(int $eventId): void
    {
        Cache::forget("event:{$eventId}:held-seats");
    }

    private function holderKey(?int $userId, ?string $sessionId): string
    {
        if ($userId) {
            return "user:{$userId}";
        }

        return 'session:'.($sessionId ?? 'guest');
    }

    private function seatLockKey(int $seatId): string
    {
        return "seat:hold:{$seatId}";
    }

    private function holderSeatsKey(string $holderKey): string
    {
        return "holder:seats:{$holderKey}";
    }
}

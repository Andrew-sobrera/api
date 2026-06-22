<?php

namespace App\Services;

use App\Repositories\EventTicketRepository;
use Illuminate\Support\Facades\Cache;

class TicketAvailabilityCacheService
{
    public function __construct(
        protected EventTicketRepository $eventTicketRepository
    ) {
    }

    public function cacheKey(int $eventTicketId): string
    {
        return "event_ticket:{$eventTicketId}:available";
    }

    public function loadStock(int $eventTicketId): int
    {
        $key = $this->cacheKey($eventTicketId);

        return (int) Cache::rememberForever($key, function () use ($eventTicketId) {
            return $this->eventTicketRepository->findById($eventTicketId)->quantity;
        });
    }

    public function setAvailable(int $eventTicketId, int $quantity): void
    {
        Cache::put($this->cacheKey($eventTicketId), $quantity);
    }

    public function reserve(int $eventTicketId, int $quantity): bool
    {
        $key = $this->cacheKey($eventTicketId);

        if (! Cache::has($key)) {
            $this->loadStock($eventTicketId);
        }

        $available = (int) Cache::get($key, 0);

        if ($available < $quantity) {
            return false;
        }

        Cache::decrement($key, $quantity);

        return true;
    }

    public function release(int $eventTicketId, int $quantity): void
    {
        $key = $this->cacheKey($eventTicketId);

        if (! Cache::has($key)) {
            $this->loadStock($eventTicketId);
        }

        Cache::increment($key, $quantity);
    }
}

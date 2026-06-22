<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\SectorStatus;
use App\Enums\TicketEventStatus;
use App\Models\Event;
use App\Models\EventSector;
use App\Models\TicketBatch;
use App\Models\TicketEvent;
use Illuminate\Support\Facades\DB;

class EventTicketSetupService
{
    public function setup(Event $event, array $data): void
    {
        match ($data['ticket_type']) {
            'simple' => $this->setupSimple($event, $data),
            'sector' => $this->setupSector($event, $data),
            'batch' => $this->setupBatch($event, $data),
            default => $this->setupSimple($event, $data),
        };

        if (! empty($data['has_seats'])) {
            if (! empty($data['map_template_id'])) {
                app(VenueMapTemplateService::class)->applyToEvent(
                    $event->fresh(),
                    (int) $data['map_template_id'],
                );
            } elseif (! empty($data['seats_config'])) {
                app(SeatMapService::class)->generateFromConfig($event, $data['seats_config']);
            }
        }
    }

    private function setupSimple(Event $event, array $data): void
    {
        TicketEvent::create([
            'event_id' => $event->id,
            'name' => $data['ticket']['name'] ?? 'Ingresso',
            'price' => (int) $data['ticket']['price'],
            'quantity' => (int) $data['ticket']['quantity'],
            'status' => TicketEventStatus::ACTIVE,
        ]);
    }

    private function setupSector(Event $event, array $data): void
    {
        foreach ($data['sectors'] as $index => $sectorData) {
            $sector = EventSector::create([
                'event_id' => $event->id,
                'name' => $sectorData['name'],
                'description' => $sectorData['description'] ?? null,
                'sort_order' => $index,
                'status' => SectorStatus::ACTIVE,
            ]);

            $ticket = TicketEvent::create([
                'event_id' => $event->id,
                'sector_id' => $sector->id,
                'name' => $sectorData['name'],
                'price' => (int) $sectorData['price'],
                'quantity' => (int) $sectorData['quantity'],
                'status' => TicketEventStatus::ACTIVE,
            ]);

            if (! empty($sectorData['batches'])) {
                $this->createBatches($ticket, $sector, $sectorData['batches']);
            }
        }
    }

    private function setupBatch(Event $event, array $data): void
    {
        $totalQuantity = array_sum(array_column($data['batches'], 'quantity'));

        $ticket = TicketEvent::create([
            'event_id' => $event->id,
            'name' => $data['ticket']['name'] ?? 'Ingresso',
            'price' => (int) ($data['batches'][0]['price'] ?? 0),
            'quantity' => $totalQuantity,
            'status' => TicketEventStatus::ACTIVE,
        ]);

        $this->createBatches($ticket, null, $data['batches']);
    }

    private function createBatches(TicketEvent $ticket, ?EventSector $sector, array $batches): void
    {
        $firstActiveSet = false;

        foreach ($batches as $index => $batchData) {
            $status = BatchStatus::PENDING;

            if (! $firstActiveSet) {
                $status = BatchStatus::ACTIVE;
                $firstActiveSet = true;
            }

            TicketBatch::create([
                'ticket_event_id' => $ticket->id,
                'sector_id' => $sector?->id,
                'name' => $batchData['name'],
                'quantity' => (int) $batchData['quantity'],
                'sold_quantity' => 0,
                'price' => (int) $batchData['price'],
                'starts_at' => $batchData['starts_at'] ?? now(),
                'ends_at' => $batchData['ends_at'] ?? null,
                'status' => $status,
                'sort_order' => $index,
            ]);
        }
    }

    public function sync(Event $event, array $data): void
    {
        DB::transaction(function () use ($event, $data) {
            $event->tickets()->each(function (TicketEvent $ticket) {
                $ticket->batches()->delete();
            });
            $event->tickets()->delete();
            $event->sectors()->each(function (EventSector $sector) {
                $sector->seatRows()->each(function ($row) {
                    $row->seats()->delete();
                });
                $sector->seatRows()->delete();
                $sector->seats()->delete();
            });
            $event->sectors()->delete();
            $event->venueMap()?->delete();

            $this->setup($event, $data);
        });
    }
}

<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketEvent>
 */
class TicketEventFactory extends Factory
{
    protected $model = TicketEvent::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Pista',
            'price' => 5000,
            'quantity' => 50,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'date' => now()->addWeek(),
            'category' => 'music',
            'status' => 'active',
            'ticket_type' => 'simple',
            'slug' => fake()->unique()->slug(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventDivision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventDivision>
 */
class EventDivisionFactory extends Factory
{
    protected $model = EventDivision::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->unique()->word(),
            'status' => 'active',
        ];
    }
}

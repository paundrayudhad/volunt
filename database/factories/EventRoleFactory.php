<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRole>
 */
class EventRoleFactory extends Factory
{
    protected $model = EventRole::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'division_id' => EventDivision::factory(),
            'name' => fake()->unique()->word(),
            'quota' => 5,
            'accepted_count' => 0,
            'status' => 'active',
        ];
    }
}

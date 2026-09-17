<?php

namespace Database\Factories;

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
        $division = EventDivision::factory()->create();

        return [
            'event_id' => $division->event_id,
            'division_id' => $division->id,
            'name' => fake()->unique()->word(),
            'quota' => 5,
            'accepted_count' => 0,
            'status' => 'active',
        ];
    }
}

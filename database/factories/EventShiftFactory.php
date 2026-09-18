<?php

namespace Database\Factories;

use App\Models\EventRole;
use App\Models\EventShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventShift>
 */
class EventShiftFactory extends Factory
{
    protected $model = EventShift::class;

    public function definition(): array
    {
        $role = EventRole::factory()->create();
        $start = fake()->dateTimeBetween('+1 week', '+2 months');

        return [
            'event_id' => $role->event_id,
            'division_id' => $role->division_id,
            'role_id' => $role->id,
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+4 hours'),
            'status' => 'active',
            'filled_count' => 0,
        ];
    }
}

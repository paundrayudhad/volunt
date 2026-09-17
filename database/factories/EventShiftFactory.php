<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventDivision;
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
        $start = fake()->dateTimeBetween('+1 week', '+2 months');

        return [
            'event_id' => Event::factory(),
            'division_id' => EventDivision::factory(),
            'role_id' => EventRole::factory(),
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+4 hours'),
            'status' => 'active',
        ];
    }
}

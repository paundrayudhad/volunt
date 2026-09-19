<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\EventShift;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        $registration = Registration::factory()->create();

        $shift = EventShift::factory()->create([
            'event_id' => $registration->event_id,
            'division_id' => $registration->role->division_id,
            'role_id' => $registration->role_id,
        ]);

        return [
            'registration_id' => $registration->id,
            'user_id' => $registration->user_id,
            'event_id' => $registration->event_id,
            'division_id' => $registration->role->division_id,
            'role_id' => $registration->role_id,
            'shift_id' => $shift->id,
            'status' => 'assigned',
        ];
    }
}

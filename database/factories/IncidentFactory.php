<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'category' => 'security',
            'priority' => 'medium',
            'location' => 'Pintu masuk utama',
            'description' => 'Kabel sound terkelupas.',
            'reporter_id' => User::factory(),
            'status' => 'open',
        ];
    }
}

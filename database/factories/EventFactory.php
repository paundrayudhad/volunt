<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+2 months');

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Jakarta',
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+2 days'),
            'status' => 'draft',
        ];
    }
}

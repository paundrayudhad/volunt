<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    protected $model = Artist::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'genre' => 'Pop',
            'stage' => 'Panggung utama',
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => Artist::DEFAULT_DURATION,
            'contact_name' => fake()->name(),
            'contact_phone' => '081234567890',
            'status' => 'scheduled',
            'attendance' => 'expected',
        ];
    }
}

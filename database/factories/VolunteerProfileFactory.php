<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerProfile>
 */
class VolunteerProfileFactory extends Factory
{
    protected $model = VolunteerProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'visibility' => 'organizers_only',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\EventRole;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    public function definition(): array
    {
        $role = EventRole::factory()->create();

        return [
            'user_id' => User::factory(),
            'event_id' => $role->event_id,
            'role_id' => $role->id,
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}

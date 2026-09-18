<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventCustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventCustomField>
 */
class EventCustomFieldFactory extends Factory
{
    protected $model = EventCustomField::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'label' => fake()->sentence(3),
            'type' => 'text',
            'required' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}

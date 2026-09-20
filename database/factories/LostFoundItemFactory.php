<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\LostFoundItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LostFoundItem>
 */
class LostFoundItemFactory extends Factory
{
    protected $model = LostFoundItem::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'kind' => 'found',
            'item_name' => 'Dompet kulit cokelat',
            'description' => 'Ditemukan di dekat panggung utama.',
            'location' => 'Posko informasi',
            'occurred_at' => now(),
            'reporter_id' => User::factory(),
            'status' => 'found',
        ];
    }
}

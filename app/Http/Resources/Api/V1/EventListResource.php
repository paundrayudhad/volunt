<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'organization' => [
                'id' => $this->organization?->id,
                'name' => $this->organization?->name,
                'slug' => $this->organization?->slug,
            ],
            'category' => $this->category,
            'venue' => $this->venue,
            'address' => $this->address,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'registration_start_at' => $this->registration_start_at?->toIso8601String(),
            'registration_end_at' => $this->registration_end_at?->toIso8601String(),
            'status' => $this->status,
        ];
    }
}

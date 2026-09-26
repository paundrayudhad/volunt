<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventDetailResource extends JsonResource
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
            'description' => $this->description,
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
            'roles' => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'quota' => $role->quota,
            ]),
            'shifts' => $this->shifts->map(fn ($shift) => [
                'id' => $shift->id,
                'location' => $shift->location,
                'start_at' => $shift->start_at?->toIso8601String(),
                'end_at' => $shift->end_at?->toIso8601String(),
                'capacity' => $shift->capacity,
            ]),
            'custom_fields' => $this->customFields->map(fn ($field) => [
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
                'required' => (bool) $field->required,
                'placeholder' => $field->placeholder,
                'options' => $field->options,
            ]),
        ];
    }
}

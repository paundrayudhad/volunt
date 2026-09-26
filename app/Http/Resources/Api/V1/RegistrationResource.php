<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Registration
 */
class RegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'event' => [
                'id' => $this->event?->id,
                'name' => $this->event?->name,
                'slug' => $this->event?->slug,
                'status' => $this->event?->status,
            ],
            'role' => [
                'id' => $this->role?->id,
                'name' => $this->role?->name,
            ],
            'answers' => $this->answers->map(fn ($ans) => [
                'custom_field_id' => $ans->event_custom_field_id,
                'value_text' => $ans->value_text,
                'value_jsonb' => $ans->value_jsonb,
            ]),
        ];
    }
}

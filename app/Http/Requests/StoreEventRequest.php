<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Event::class, $this->route('organization')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $org = $this->route('organization');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('events', 'slug')->where('organization_id', $org->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'venue' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'registration_start_at' => ['nullable', 'date'],
            'registration_end_at' => ['nullable', 'date', 'after:registration_start_at'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'contact' => ['nullable', 'array'],
            'branding' => ['nullable', 'array'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'privacy_notice' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

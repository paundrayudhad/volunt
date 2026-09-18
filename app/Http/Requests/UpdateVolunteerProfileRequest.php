<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVolunteerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:128'],
            'education' => ['nullable', 'string', 'max:128'],
            'experience' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:128'],
            'portfolio_url' => ['nullable', 'url', 'max:512'],
            'social_links' => ['nullable', 'array'],
            'availability' => ['nullable', 'array'],
            'visibility' => ['required', 'string', Rule::in(['public', 'organizers_only', 'private'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'emergency_contact' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $registration = $this->route('registration');

        return $registration instanceof Registration
            && $this->user()->can('review', $registration);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['accepted', 'rejected', 'waitlisted', 'cancelled'])],
            'reason' => ['required_if:action,rejected', 'nullable', 'string', 'max:2000'],
        ];
    }
}

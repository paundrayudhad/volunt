<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('organizationRequest'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}

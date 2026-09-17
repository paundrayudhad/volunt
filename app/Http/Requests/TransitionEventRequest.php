<?php

namespace App\Http\Requests;

use App\Services\EventService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('event'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(EventService::TRANSITIONS))],
            'reason' => ['required_if:status,cancelled', 'nullable', 'string', 'max:2000'],
        ];
    }
}

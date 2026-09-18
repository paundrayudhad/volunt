<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && $this->user()->can('viewAny', [Registration::class, $event]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
            'action' => ['required', 'string', Rule::in(['accepted', 'rejected', 'waitlisted', 'cancelled'])],
            'reason' => ['required_if:action,rejected', 'nullable', 'string', 'max:2000'],
        ];
    }
}

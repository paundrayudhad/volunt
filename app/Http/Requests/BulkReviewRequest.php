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

        if (! $event instanceof Event) {
            return false;
        }

        $sample = Registration::where('event_id', $event->id)->first();

        if ($sample instanceof Registration) {
            return $this->user()->can('review', $sample);
        }

        return $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('registration.review');
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

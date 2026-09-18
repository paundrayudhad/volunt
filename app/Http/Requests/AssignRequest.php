<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class AssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        if (! $event instanceof Event) {
            return false;
        }

        $sample = Assignment::where('event_id', $event->id)->first();

        if ($sample instanceof Assignment) {
            return $this->user()->can('manage', $sample);
        }

        return $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('assignment.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'registration_id' => ['required', 'integer'],
            'shift_id' => ['required', 'integer'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}

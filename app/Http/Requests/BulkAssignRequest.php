<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class BulkAssignRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
            'shift_id' => ['required', 'integer'],
        ];
    }
}

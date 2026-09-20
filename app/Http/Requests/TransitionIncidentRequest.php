<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        $insiden = $this->route('incident');

        if ($insiden instanceof Incident) {
            return $this->user()->can('manage', $insiden);
        }

        return $event instanceof Event
            && $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('incident.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to' => ['required', Rule::in(Incident::STATUSES)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.required' => 'Tahap tujuan wajib diisi.',
            'to.in' => 'Tahap tujuan tidak dikenal.',
        ];
    }
}

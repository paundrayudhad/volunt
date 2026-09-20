<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;

class AssignIncidentRequest extends FormRequest
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
            'assignee_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'assignee_id.required' => 'Petugas wajib dipilih.',
            'assignee_id.exists' => 'Petugas tidak ditemukan.',
        ];
    }
}

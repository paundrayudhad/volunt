<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;

class ReopenIncidentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembukaan ulang wajib diisi.',
            'reason.min' => 'Alasan pembukaan ulang minimal 10 karakter.',
        ];
    }
}

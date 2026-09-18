<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class ManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        if (! $event instanceof Event) {
            return false;
        }

        return $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('attendance.record');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pencatatan manual wajib diisi.',
            'reason.min' => 'Alasan pencatatan manual minimal 10 karakter.',
            'idempotency_key.uuid' => 'Kunci idempotency tidak valid.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class ScanAttendanceRequest extends FormRequest
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
            'token' => ['required', 'string', 'size:64'],
            'action' => ['required', 'in:check_in,check_out'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'token.required' => 'Kode kehadiran wajib diisi.',
            'action.in' => 'Aksi kehadiran tidak valid.',
            'idempotency_key.uuid' => 'Kunci idempotency tidak valid.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Certificate;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class IssueCertificatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        if (! $event instanceof Event) {
            return false;
        }

        $sample = Certificate::where('event_id', $event->id)->first();

        if ($sample instanceof Certificate) {
            return $this->user()->can('issue', $sample);
        }

        return $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('certificate.issue');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'min_attendance_pct' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'min_attendance_pct.min' => 'Ambang kehadiran harus 1–100.',
            'min_attendance_pct.max' => 'Ambang kehadiran harus 1–100.',
        ];
    }
}

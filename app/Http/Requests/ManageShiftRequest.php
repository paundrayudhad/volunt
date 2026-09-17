<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\EventShift;
use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('shift');

        return $shift instanceof EventShift
            ? $this->user()->can('manage', $shift)
            : $this->user()->can('manage', [EventShift::class, $this->route('event')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');

        return [
            'division_id' => ['sometimes', 'integer'],
            'role_id' => [
                'nullable', 'integer',
                Rule::exists('event_roles', 'id')->where('event_id', $event->id),
            ],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'supervisor_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($event): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $aktif = OrganizationMember::where('organization_id', $event->organization_id)
                        ->where('user_id', (int) $value)
                        ->where('status', 'active')
                        ->exists();
                    if (! $aktif) {
                        $fail('Supervisor yang dipilih tidak valid.');
                    }
                },
            ],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $division = $this->route('division');

        return $division instanceof EventDivision
            ? $this->user()->can('manage', $division)
            : $this->user()->can('manage', [EventDivision::class, $this->route('event')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'supervisor_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    /** @var Event $event */
                    $event = $this->route('event');
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

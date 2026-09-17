<?php

namespace App\Http\Requests;

use App\Models\EventRole;
use Illuminate\Foundation\Http\FormRequest;

class ManageRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof EventRole
            ? $this->user()->can('manage', $role)
            : $this->user()->can('manage', [EventRole::class, $this->route('event')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'division_id' => ['sometimes', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'quota' => ['sometimes', 'integer', 'min:0'],
            'requirements' => ['nullable', 'array'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}

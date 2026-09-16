<?php

namespace App\Http\Requests;

use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invite', [OrganizationMember::class, $this->route('organization')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}

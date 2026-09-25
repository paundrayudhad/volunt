<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class SearchTalentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $org = $this->route('organization');
        $user = $this->user();

        return $org instanceof Organization
            && $user !== null
            && $user->belongsToOrganization($org->id)
            && $user->can('talent.search');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'skills' => ['nullable', 'string', 'max:100'],
        ];
    }
}

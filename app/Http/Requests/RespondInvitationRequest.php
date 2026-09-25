<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:accepted,declined'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'action.required' => 'Aksi respon wajib dipilih.',
            'action.in' => 'Pilihan aksi respon tidak valid.',
        ];
    }
}

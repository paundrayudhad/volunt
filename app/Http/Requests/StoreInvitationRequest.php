<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        $user = $this->user();

        return $event instanceof Event
            && $user !== null
            && $user->belongsToOrganization($event->organization_id)
            && $user->can('talent.invite');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'role_id' => ['nullable', 'exists:event_roles,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Relawan tujuan wajib dipilih.',
            'user_id.exists' => 'Relawan tidak ditemukan.',
            'role_id.exists' => 'Role tidak ditemukan.',
            'message.max' => 'Pesan maksimal 1000 karakter.',
        ];
    }
}

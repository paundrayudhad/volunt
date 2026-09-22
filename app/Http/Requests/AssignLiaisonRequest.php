<?php

namespace App\Http\Requests;

use App\Models\Artist;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class AssignLiaisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $artis = $this->route('artist');
        $event = $this->route('event');

        if ($artis instanceof Artist) {
            return $this->user()->can('manage', $artis);
        }

        return $event instanceof Event
            && $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('artist.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Volunteer LO wajib dipilih.',
            'user_id.integer' => 'Volunteer LO tidak valid.',
            'user_id.exists' => 'Volunteer tidak ditemukan.',
        ];
    }
}

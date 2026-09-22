<?php

namespace App\Http\Requests;

use App\Models\Artist;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionArtistRequest extends FormRequest
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
            'to' => ['required', Rule::in(Artist::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.required' => 'Tahap tujuan wajib diisi.',
            'to.in' => 'Tahap tujuan tidak dikenal.',
            'note.string' => 'Catatan harus berupa teks.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}

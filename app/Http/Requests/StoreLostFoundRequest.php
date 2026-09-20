<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\LostFoundItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLostFoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('incident.report');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(LostFoundItem::KINDS)],
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'location' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kind.required' => 'Jenis laporan wajib diisi.',
            'kind.in' => 'Jenis laporan tidak dikenal.',
            'item_name.required' => 'Nama barang wajib diisi.',
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Format foto: jpg, jpeg, png, atau webp.',
            'photo.max' => 'Ukuran foto maksimal 5MB.',
        ];
    }
}

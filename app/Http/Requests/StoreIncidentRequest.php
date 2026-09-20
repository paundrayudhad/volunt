<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
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
            'category' => ['required', Rule::in(Incident::CATEGORIES)],
            'priority' => ['nullable', Rule::in(Incident::PRIORITIES)],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'lost_found_item_id' => ['nullable', 'integer', 'exists:lost_found_items,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category.required' => 'Kategori insiden wajib diisi.',
            'category.in' => 'Kategori insiden tidak dikenal.',
            'priority.in' => 'Prioritas insiden tidak dikenal.',
            'location.required' => 'Lokasi insiden wajib diisi.',
            'description.required' => 'Deskripsi insiden wajib diisi.',
            'description.min' => 'Deskripsi insiden minimal 10 karakter.',
            'lost_found_item_id.exists' => 'Item tertaut tidak ditemukan.',
        ];
    }
}

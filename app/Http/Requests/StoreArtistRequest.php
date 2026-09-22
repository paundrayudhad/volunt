<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class StoreArtistRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('artist.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:100'],
            'stage' => ['nullable', 'string', 'max:100'],
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'performance_order' => ['nullable', 'integer', 'min:1'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'rider_text' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama artis wajib diisi.',
            'name.string' => 'Nama artis harus berupa teks.',
            'name.max' => 'Nama artis maksimal 255 karakter.',
            'genre.string' => 'Genre harus berupa teks.',
            'genre.max' => 'Genre maksimal 100 karakter.',
            'stage.string' => 'Panggung harus berupa teks.',
            'stage.max' => 'Panggung maksimal 100 karakter.',
            'scheduled_at.date' => 'Format jadwal tidak valid.',
            'duration_minutes.integer' => 'Durasi harus berupa angka menit.',
            'duration_minutes.min' => 'Durasi minimal 15 menit.',
            'duration_minutes.max' => 'Durasi maksimal 240 menit.',
            'performance_order.integer' => 'Urutan tampil harus berupa angka.',
            'performance_order.min' => 'Urutan tampil minimal 1.',
            'contact_name.string' => 'Nama kontak harus berupa teks.',
            'contact_name.max' => 'Nama kontak maksimal 255 karakter.',
            'contact_phone.string' => 'Nomor kontak harus berupa teks.',
            'contact_phone.max' => 'Nomor kontak maksimal 50 karakter.',
            'rider_text.string' => 'Rider harus berupa teks.',
            'rider_text.max' => 'Rider maksimal 2000 karakter.',
        ];
    }
}

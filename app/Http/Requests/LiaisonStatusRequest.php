<?php

namespace App\Http\Requests;

use App\Models\Artist;
use App\Models\ArtistLiaison;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LiaisonStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $artis = $this->route('artist');

        if (! $artis instanceof Artist) {
            return false;
        }

        return ArtistLiaison::where('artist_id', $artis->id)
            ->where('user_id', $this->user()->id)
            ->exists();
    }

    protected function failedAuthorization(): void
    {
        // Spec Task 6: di luar scope dampingan → 404, bukan 403.
        abort(404);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to' => ['nullable', 'required_without:attendance', Rule::in(Artist::STATUSES)],
            'attendance' => ['nullable', 'required_without:to', Rule::in(Artist::ATTENDANCES)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.required_without' => 'Tahap tujuan atau kehadiran wajib diisi.',
            'to.in' => 'Tahap tujuan tidak dikenal.',
            'attendance.required_without' => 'Tahap tujuan atau kehadiran wajib diisi.',
            'attendance.in' => 'Status kehadiran tidak dikenal.',
            'note.string' => 'Catatan harus berupa teks.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}

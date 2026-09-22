<?php

namespace App\Http\Requests;

use App\Models\Artist;
use App\Models\ArtistLiaison;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ToggleRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $artis = $this->route('artist');

        if (! $artis instanceof Artist) {
            return false;
        }

        // Rute own-scoped my/liaison tak punya {event}: gate keanggotaan LO saja.
        if ($this->route('event') === null) {
            return ArtistLiaison::where('artist_id', $artis->id)
                ->where('user_id', $user->id)
                ->exists();
        }

        return $user->can('manage', $artis) || $user->can('liaise', $artis);
    }

    protected function failedAuthorization(): void
    {
        // Spec Task 6: di luar scope dampingan → 404, bukan 403.
        if ($this->route('event') === null) {
            abort(404);
        }

        throw new AuthorizationException('Aksi ini tidak diizinkan.');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fulfilled' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fulfilled.required' => 'Status rider wajib diisi.',
            'fulfilled.boolean' => 'Status rider tidak valid.',
        ];
    }
}

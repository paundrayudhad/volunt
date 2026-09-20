<?php

namespace App\Http\Requests;

use App\Models\Certificate;
use Illuminate\Foundation\Http\FormRequest;

class RevokeCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sertifikat = $this->route('certificate');

        return $sertifikat instanceof Certificate
            && $this->user()->can('revoke', $sertifikat);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pencabutan wajib diisi.',
            'reason.min' => 'Alasan pencabutan minimal 10 karakter.',
        ];
    }
}

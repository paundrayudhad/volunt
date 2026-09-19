<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;

class ReassignAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        if (! $assignment instanceof Assignment) {
            return false;
        }

        return $this->user()->can('manage', $assignment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'integer'],
        ];
    }
}

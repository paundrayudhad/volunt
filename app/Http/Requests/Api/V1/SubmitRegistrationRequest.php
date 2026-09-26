<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'event_role_id' => ['required', 'integer'],
            'answers' => ['nullable', 'array'],
            'answers.*.event_custom_field_id' => ['required_with:answers', 'integer'],
            'answers.*.value_text' => ['nullable', 'string'],
            'answers.*.value_jsonb' => ['nullable'],
            'answers.*.file_path' => ['nullable', 'string'],
        ];
    }
}

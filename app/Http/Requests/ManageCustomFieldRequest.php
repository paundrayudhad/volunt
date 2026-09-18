<?php

namespace App\Http\Requests;

use App\Models\EventCustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageCustomFieldRequest extends FormRequest
{
    public const OPTION_TYPES = ['select', 'multi_select', 'radio', 'checkbox'];

    public function authorize(): bool
    {
        $field = $this->route('field');

        return $field instanceof EventCustomField
            ? $this->user()->can('manage', $field)
            : $this->user()->can('manage', [EventCustomField::class, $this->route('event')]);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('options') && is_string($this->input('options_text'))) {
            $options = [];
            foreach (preg_split('/\r\n|\r|\n/', $this->input('options_text')) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                [$label, $value] = array_pad(explode('|', $line, 2), 2, '');
                $options[] = ['label' => trim($label), 'value' => trim($value) === '' ? trim($label) : trim($value)];
            }
            $this->merge(['options' => $options]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $optionsRequired = in_array($this->input('type'), self::OPTION_TYPES, true);

        return [
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(EventCustomField::TYPES)],
            'required' => ['sometimes', 'boolean'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'validation_rule' => ['nullable', 'string', 'max:512'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'options' => [$optionsRequired ? 'nullable' : 'prohibited', 'array', 'max:50'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.value' => ['required', 'string', 'max:255'],
            'options.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

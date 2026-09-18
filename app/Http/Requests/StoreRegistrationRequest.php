<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Services\SecurityService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('eventPublic');
        $fields = $event->customFields()->with('options')->where('is_active', true)->orderBy('sort_order')->get();

        $rules = [
            'role_id' => ['required', 'integer', Rule::exists('event_roles', 'id')->where('event_id', $event->id)],
            'idempotency_key' => ['required', 'uuid'],
            'answers' => ['nullable', 'array'],
        ];

        foreach ($fields as $field) {
            $key = "answers.{$field->id}";
            $required = $field->required ? ['required'] : ['nullable'];
            $rules[$key] = match ($field->type) {
                'email' => [...$required, 'email', 'max:255'],
                'number' => [...$required, 'numeric'],
                'date' => [...$required, 'date'],
                'time' => [...$required, 'date_format:H:i'],
                'url' => [...$required, 'url', 'max:512'],
                'phone' => [...$required, 'string', 'max:32'],
                'select', 'radio' => [...$required, Rule::in($field->options->pluck('value')->all())],
                'multi_select', 'checkbox' => [...$required, 'array'],
                'file' => [...$required, 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png', function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    $this->validateFileSignature($attribute, $value, $fail);
                }],
                default => [...$required, 'string', 'max:2000'],
            };
            if (in_array($field->type, ['multi_select', 'checkbox'], true)) {
                $rules["{$key}.*"] = ['string', Rule::in($field->options->pluck('value')->all())];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $answers = $this->input('answers', []);
            if (! is_array($answers)) {
                return;
            }
            $event = $this->route('eventPublic');
            $allowed = $event instanceof Event
                ? $event->customFields()->where('is_active', true)->pluck('id')
                    ->map(fn ($id) => (string) $id)->all()
                : [];
            foreach (array_keys($answers) as $key) {
                if (! in_array((string) $key, $allowed, true)) {
                    $validator->errors()->add("answers.{$key}", 'Jawaban field tidak dikenal pada event ini.');
                }
            }
        });
    }

    private function validateFileSignature(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }
        $realMime = (string) finfo_file($value->getRealPath(), FILEINFO_MIME_TYPE);
        $expected = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ][strtolower($value->getClientOriginalExtension())] ?? null;

        if ($expected === null || $realMime !== $expected) {
            app(SecurityService::class)->record($this->user(), 'file_upload_rejected', [
                'attribute' => $attribute,
                'client_mime' => $value->getClientMimeType(),
                'real_mime' => $realMime,
            ]);
            $fail('File yang diunggah tidak valid.');
        }
    }
}

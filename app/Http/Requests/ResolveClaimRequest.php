<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\LostFoundItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        $item = $this->route('lostFoundItem');

        if ($item instanceof LostFoundItem) {
            return $this->user()->can('manage', $item);
        }

        return $event instanceof Event
            && $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('lostfound.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['returned', 'rejected'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan klaim wajib diisi.',
            'decision.in' => 'Keputusan klaim tidak dikenal.',
        ];
    }
}

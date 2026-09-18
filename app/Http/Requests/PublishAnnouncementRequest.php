<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        if (! $event instanceof Event) {
            return false;
        }

        $sample = Announcement::where('event_id', $event->id)->first();

        if ($sample instanceof Announcement) {
            return $this->user()->can('publish', $sample);
        }

        return $this->user()->belongsToOrganization($event->organization_id)
            && $this->user()->can('announcement.publish');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'target_type' => ['required', Rule::in(['event', 'division', 'role', 'shift', 'individual'])],
            'target_id' => ['required_unless:target_type,event', 'nullable', 'integer'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul pengumuman wajib diisi.',
            'body.required' => 'Isi pengumuman wajib diisi.',
            'target_type.required' => 'Target pengumuman wajib dipilih.',
            'target_type.in' => 'Target pengumuman tidak valid.',
            'target_id.required_unless' => 'ID target wajib diisi untuk target selain seluruh event.',
            'expires_at.after' => 'Waktu kedaluwarsa harus di masa depan.',
        ];
    }
}

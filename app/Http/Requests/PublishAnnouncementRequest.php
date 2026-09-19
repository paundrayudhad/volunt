<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Registration;
use Closure;
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
        /** @var Event $event */
        $event = $this->route('event');
        $eventId = $event->id;
        $tipe = $this->input('target_type');

        $target = ['required_unless:target_type,event', 'nullable', 'integer'];

        if ($tipe === 'division') {
            $target[] = Rule::exists('event_divisions', 'id')->where('event_id', $eventId);
        } elseif ($tipe === 'role') {
            $target[] = Rule::exists('event_roles', 'id')->where('event_id', $eventId);
        } elseif ($tipe === 'shift') {
            $target[] = Rule::exists('event_shifts', 'id')->where('event_id', $eventId);
        } elseif ($tipe === 'individual') {
            $target[] = function (string $attribute, mixed $value, Closure $fail) use ($eventId): void {
                if ($value === null || $value === '') {
                    return;
                }
                $diterima = Registration::where('event_id', $eventId)
                    ->where('user_id', (int) $value)
                    ->where('status', 'accepted')
                    ->exists();
                if (! $diterima) {
                    $fail('Pengguna target belum diterima di event ini.');
                }
            };
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'target_type' => ['required', Rule::in(['event', 'division', 'role', 'shift', 'individual'])],
            'target_id' => $target,
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
            'target_id.exists' => 'ID target tidak termasuk event ini.',
            'expires_at.after' => 'Waktu kedaluwarsa harus di masa depan.',
        ];
    }
}

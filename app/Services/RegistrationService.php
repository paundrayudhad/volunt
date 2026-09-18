<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Registration;
use App\Models\RegistrationAnswer;
use App\Models\RegistrationStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    public function __construct(private AuditLogService $audit, private QuotaService $quota) {}

    /** @param array<int, mixed> $answers */
    public function submit(Event $event, EventRole $role, User $user, array $answers, string $idempotencyKey): Registration
    {
        abort_unless($event->status === 'registration_open', 422, 'Registrasi event ini tidak sedang dibuka.');
        abort_if($event->isTerminal(), 422, 'Event sudah berakhir atau dibatalkan.');
        abort_unless((int) $role->event_id === (int) $event->id, 422, 'Role tidak termasuk event ini.');
        abort_unless($event->organization?->status === 'active', 422, 'Organisasi tidak aktif.');

        return DB::transaction(function () use ($event, $role, $user, $answers, $idempotencyKey): Registration {
            $existing = Registration::where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof Registration) {
                return $existing; // idempotent replay
            }
            $duplikat = Registration::where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->whereIn('status', ['pending', 'under_review', 'accepted', 'waitlisted'])
                ->exists();
            abort_if($duplikat, 422, 'Anda sudah memiliki pendaftaran aktif pada event ini.');
            $reg = Registration::unguarded(fn (): Registration => Registration::create([
                'user_id' => $user->id, 'event_id' => $event->id, 'role_id' => $role->id,
                'status' => 'pending', 'submitted_at' => now(), 'idempotency_key' => $idempotencyKey,
            ]));
            // simpan answers (divalidasi di Form Request; service percaya payload tervalidasi)
            foreach ($answers as $jawab) {
                if (! is_array($jawab) || ! isset($jawab['event_custom_field_id'])) {
                    continue;
                }
                $payload = array_intersect_key($jawab, array_flip(['event_custom_field_id', 'value_text', 'value_jsonb', 'file_path']));
                RegistrationAnswer::unguarded(fn (): mixed => $reg->answers()->create($payload));
            }
            RegistrationStatusHistory::unguarded(fn (): mixed => $reg->histories()->create([
                'from_status' => null, 'to_status' => 'pending', 'changed_by' => $user->id, 'reason' => null,
            ]));
            $this->audit->record($user, 'registration.submitted', Registration::class, $reg->id, ['organization_id' => $event->organization_id, 'event_id' => $event->id]);

            return $reg;
        });
    }

    public function withdraw(Registration $reg, User $user): Registration
    {
        abort_unless((int) $reg->user_id === (int) $user->id, 403, 'Anda tidak berhak menarik pendaftaran ini.');

        return DB::transaction(fn (): Registration => $this->terapkanStatus($reg, 'withdrawn', $user, null));
    }

    public function review(Registration $reg, string $to, User $actor, ?string $reason = null): Registration
    {
        return DB::transaction(fn (): Registration => $this->terapkanStatus($reg, $to, $actor, $reason));
    }

    public function cancel(Registration $reg, User $actor, ?string $reason = null): Registration
    {
        return $this->review($reg, 'cancelled', $actor, $reason);
    }

    /**
     * @param  array<int>  $ids
     * @return array<Registration>
     */
    public function bulkReview(Event $event, array $ids, string $to, User $actor, ?string $reason = null): array
    {
        abort_if(count($ids) > 50, 422, 'Bulk review maksimal 50 pendaftaran.');
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $daftar = Registration::where('event_id', $event->id)->whereIn('id', $ids)->get();
        abort_unless($daftar->count() === count($ids), 404, 'Sebagian pendaftaran tidak termasuk event ini.');

        return DB::transaction(function () use ($daftar, $to, $actor, $reason): array {
            $hasil = [];
            foreach ($daftar as $reg) {
                $hasil[] = $this->terapkanStatus($reg, $to, $actor, $reason);
            }

            return $hasil;
        });
    }

    private function terapkanStatus(Registration $reg, string $to, User $actor, ?string $reason): Registration
    {
        abort_unless(in_array($to, Registration::TRANSITIONS[$reg->status] ?? [], true), 422, 'Transisi status tidak valid.');
        if ($to === 'rejected') {
            abort_unless(trim((string) $reason) !== '', 422, 'Alasan penolakan wajib diisi.');
        }
        $dari = $reg->status;
        if ($to === 'accepted') {
            $this->quota->accept($reg->role()->firstOrFail());
        }
        if ($to === 'cancelled' && $dari === 'accepted') {
            $this->quota->release($reg->role()->firstOrFail());
        }
        $reg->forceFill([
            'status' => $to,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => $to === 'rejected' ? $reason : null,
        ])->save();
        RegistrationStatusHistory::unguarded(fn (): mixed => $reg->histories()->create([
            'from_status' => $dari, 'to_status' => $to, 'changed_by' => $actor->id, 'reason' => $reason,
        ]));
        $event = $reg->event()->firstOrFail();
        $this->audit->record($actor, "registration.{$to}", Registration::class, $reg->id, [
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
        ]);

        return $reg->refresh();
    }
}

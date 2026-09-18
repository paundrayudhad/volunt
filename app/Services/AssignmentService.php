<?php

namespace App\Services;

use App\Exceptions\ShiftFullException;
use App\Models\Assignment;
use App\Models\AssignmentHistory;
use App\Models\Event;
use App\Models\EventShift;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignmentService
{
    public function __construct(private AuditLogService $audit) {}

    public function assign(Registration $registration, EventShift $shift, User $actor, ?string $location = null): Assignment
    {
        return DB::transaction(function () use ($registration, $shift, $actor, $location): Assignment {
            $this->validasiPenugasan($registration, $shift);
            $this->isiKuota($shift);
            $this->pastikanTidakBentrok($registration->user_id, $shift);

            $assignment = Assignment::unguarded(fn (): Assignment => Assignment::create([
                'registration_id' => $registration->id,
                'user_id' => $registration->user_id,
                'event_id' => $registration->event_id,
                'division_id' => $registration->role->division_id,
                'role_id' => $registration->role_id,
                'shift_id' => $shift->id,
                'location' => $location ?? $shift->location,
                'supervisor_id' => $shift->supervisor_id,
                'status' => 'assigned',
            ]));
            $this->catat($assignment, null, 'assigned', $actor, null);

            return $assignment->refresh();
        });
    }

    public function reassign(Assignment $assignment, EventShift $shiftBaru, User $actor): Assignment
    {
        return DB::transaction(function () use ($assignment, $shiftBaru, $actor): Assignment {
            $assignment = $assignment->refresh();
            abort_unless($assignment->isActive(), 422, 'Hanya assignment aktif yang bisa dipindahkan.');
            $registration = $assignment->registration()->firstOrFail();
            $this->validasiPenugasan($registration, $shiftBaru, $assignment->id);

            $shiftLamaId = (int) $assignment->shift_id;
            abort_if($shiftLamaId === (int) $shiftBaru->id, 422, 'Shift baru harus berbeda dari shift saat ini.');

            $this->isiKuota($shiftBaru);
            $this->pastikanTidakBentrok($assignment->user_id, $shiftBaru, $assignment->id);

            $shiftLama = EventShift::whereKey($shiftLamaId)->firstOrFail();
            $dari = $assignment->status;
            $assignment->forceFill([
                'shift_id' => $shiftBaru->id,
                'location' => $assignment->location ?? $shiftBaru->location,
                'supervisor_id' => $assignment->supervisor_id ?? $shiftBaru->supervisor_id,
                'status' => 'reassigned',
            ])->save();
            $this->catat($assignment, $dari, 'reassigned', $actor, "Pindah shift dari {$shiftLamaId} ke {$shiftBaru->id}.");
            $this->lepasKuota($shiftLama);

            return $assignment->refresh();
        });
    }

    public function confirm(Assignment $assignment, User $actor): Assignment
    {
        return DB::transaction(fn (): Assignment => $this->terapkanStatus($assignment->refresh(), 'confirmed', $actor, null));
    }

    public function cancel(Assignment $assignment, User $actor, ?string $reason = null): Assignment
    {
        return DB::transaction(function () use ($assignment, $actor, $reason): Assignment {
            $assignment = $assignment->refresh();
            abort_unless($assignment->isActive(), 422, 'Hanya assignment aktif yang bisa dibatalkan.');

            $assignment = $this->terapkanStatus($assignment, 'cancelled', $actor, $reason);
            $this->lepasKuota($assignment->shift()->firstOrFail());

            return $assignment->refresh();
        });
    }

    /**
     * @param  array<int>  $registrationIds
     * @return array<Assignment>
     */
    public function bulkAssign(Event $event, array $registrationIds, EventShift $shift, User $actor): array
    {
        abort_if(count($registrationIds) > 50, 422, 'Bulk assign maksimal 50 pendaftaran.');
        abort_unless((int) $shift->event_id === (int) $event->id, 422, 'Shift tidak termasuk event ini.');

        $ids = array_values(array_unique(array_map('intval', $registrationIds)));
        $daftar = Registration::where('event_id', $event->id)->whereIn('id', $ids)->get();
        abort_unless($daftar->count() === count($ids), 404, 'Sebagian pendaftaran tidak termasuk event ini.');

        return DB::transaction(function () use ($daftar, $shift, $actor): array {
            $hasil = [];
            foreach ($daftar as $registration) {
                $this->validasiPenugasan($registration, $shift);
                $this->isiKuota($shift);
                $this->pastikanTidakBentrok($registration->user_id, $shift);

                $assignment = Assignment::unguarded(fn (): Assignment => Assignment::create([
                    'registration_id' => $registration->id,
                    'user_id' => $registration->user_id,
                    'event_id' => $registration->event_id,
                    'division_id' => $registration->role->division_id,
                    'role_id' => $registration->role_id,
                    'shift_id' => $shift->id,
                    'location' => $shift->location,
                    'supervisor_id' => $shift->supervisor_id,
                    'status' => 'assigned',
                ]));
                $this->catat($assignment, null, 'assigned', $actor, null);
                $hasil[] = $assignment->refresh();
            }

            return $hasil;
        });
    }

    private function terapkanStatus(Assignment $assignment, string $to, User $actor, ?string $note): Assignment
    {
        abort_unless(in_array($to, Assignment::TRANSITIONS[$assignment->status] ?? [], true), 422, 'Transisi status tidak valid.');
        $dari = $assignment->status;
        $assignment->forceFill(['status' => $to])->save();
        $this->catat($assignment, $dari, $to, $actor, $note);

        return $assignment->refresh();
    }

    private function catat(Assignment $assignment, ?string $dari, string $to, User $actor, ?string $note): void
    {
        AssignmentHistory::unguarded(fn (): mixed => $assignment->histories()->create([
            'from_status' => $dari,
            'to_status' => $to,
            'changed_by' => $actor->id,
            'note' => $note,
            'created_at' => now(),
        ]));
        $event = $assignment->event()->firstOrFail();
        $this->audit->record($actor, "assignment.{$to}", Assignment::class, $assignment->id, [
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
        ]);
    }

    private function validasiPenugasan(Registration $registration, EventShift $shift, ?int $kecualiAssignmentId = null): void
    {
        abort_unless($registration->status === 'accepted', 422, 'Hanya pendaftaran yang diterima yang bisa ditugaskan.');
        abort_unless((int) $shift->event_id === (int) $registration->event_id, 422, 'Shift tidak termasuk event yang sama dengan pendaftaran.');
        $duplikat = Assignment::where('registration_id', $registration->id)
            ->when($kecualiAssignmentId !== null, fn ($q) => $q->where('id', '!=', $kecualiAssignmentId))
            ->exists();
        abort_if($duplikat, 422, 'Pendaftaran ini sudah memiliki assignment.');
    }

    private function isiKuota(EventShift $shift): EventShift
    {
        $terkunci = EventShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();
        if ($terkunci->capacity === null) {
            $terkunci->increment('filled_count');

            return $terkunci->refresh();
        }
        if ((int) $terkunci->filled_count >= (int) $terkunci->capacity) {
            throw new ShiftFullException;
        }
        $terkunci->increment('filled_count');

        return $terkunci->refresh();
    }

    private function lepasKuota(EventShift $shift): EventShift
    {
        $terkunci = EventShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();
        if ((int) $terkunci->filled_count > 0) {
            $terkunci->decrement('filled_count');
        }

        return $terkunci->refresh();
    }

    private function pastikanTidakBentrok(int $userId, EventShift $shift, ?int $kecualiAssignmentId = null): void
    {
        $bentrok = Assignment::where('user_id', $userId)
            ->whereIn('status', ['assigned', 'reassigned', 'confirmed'])
            ->when($kecualiAssignmentId !== null, fn ($q) => $q->where('id', '!=', $kecualiAssignmentId))
            ->whereHas('shift', fn ($q) => $q
                ->where('start_at', '<', $shift->end_at)
                ->where('end_at', '>', $shift->start_at))
            ->exists();
        abort_if($bentrok, 422, 'Jadwal bentrok dengan shift lain.');
    }
}

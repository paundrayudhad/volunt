<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\EventShift;
use App\Models\QrToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(private AuditLogService $audit) {}

    /** @return array{token: QrToken, raw: string} */
    public function issueToken(Assignment $assignment, User $actor): array
    {
        return DB::transaction(function () use ($assignment): array {
            $assignment = $assignment->refresh();

            QrToken::where('assignment_id', $assignment->id)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $mentah = bin2hex(random_bytes(32));
            $token = QrToken::unguarded(fn (): QrToken => $assignment->qrTokens()->create([
                'token_hash' => hash('sha256', $mentah),
                'expires_at' => now()->addMinutes(5),
            ]));

            return ['token' => $token->refresh(), 'raw' => $mentah];
        });
    }

    public function checkIn(string $mentah, User $actor, string $kunci, ?int $eventId = null): Attendance
    {
        return DB::transaction(function () use ($mentah, $actor, $kunci, $eventId): Attendance {
            $token = $this->cariToken($mentah, $eventId);
            $assignment = $token->assignment()->firstOrFail();
            $shift = $assignment->shift()->firstOrFail();

            $replay = Attendance::where('idempotency_key', $kunci)->first();
            if ($replay instanceof Attendance) {
                abort_unless(
                    (int) $replay->assignment_id === (int) $assignment->id
                        && (int) $replay->shift_id === (int) $shift->id,
                    404,
                    'Token tidak termasuk event ini.'
                );

                return $replay;
            }

            $this->pastikanJendela($shift);
            $this->pastikanTokenBisaDipakai($token);

            abort_unless($assignment->isActive(), 422, 'Assignment tidak aktif.');
            abort_if(
                Attendance::where('assignment_id', $assignment->id)->where('shift_id', $shift->id)->exists(),
                422,
                'Sudah check-in.'
            );

            $hadir = Attendance::unguarded(fn (): Attendance => Attendance::create([
                'assignment_id' => $assignment->id,
                'shift_id' => $shift->id,
                'event_id' => $assignment->event_id,
                'user_id' => $assignment->user_id,
                'checked_in_at' => now(),
                'method' => 'qr',
                'status' => $this->tentukanStatus($shift),
                'idempotency_key' => $kunci,
            ]));

            $token->forceFill(['used_at' => now()])->save();
            $this->catat($hadir, 'check_in', $actor);
            $event = $assignment->event()->firstOrFail();
            $this->audit->record($actor, 'attendance.check_in', Attendance::class, $hadir->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);

            return $hadir->refresh();
        });
    }

    public function checkOut(string $mentah, User $actor, string $kunci, ?int $eventId = null): Attendance
    {
        return DB::transaction(function () use ($mentah, $actor, $kunci, $eventId): Attendance {
            $token = $this->cariToken($mentah, $eventId);
            $assignment = $token->assignment()->firstOrFail();
            $shift = $assignment->shift()->firstOrFail();

            $replay = Attendance::where('idempotency_key', $kunci)->first();
            if ($replay instanceof Attendance) {
                abort_unless(
                    (int) $replay->assignment_id === (int) $assignment->id
                        && (int) $replay->shift_id === (int) $shift->id,
                    404,
                    'Token tidak termasuk event ini.'
                );

                return $replay;
            }

            $hadir = Attendance::where('assignment_id', $assignment->id)
                ->where('shift_id', $shift->id)
                ->first();
            abort_if($hadir === null || $hadir->checked_in_at === null, 422, 'Belum check-in.');

            $this->pastikanTokenBisaDipakai($token);
            abort_if($hadir->checked_out_at !== null, 422, 'Sudah check-out.');

            $this->pastikanJendela($shift);

            abort_unless($assignment->isActive(), 422, 'Assignment tidak aktif.');
            abort_if(now()->lt($hadir->checked_in_at), 422, 'Check-out harus setelah check-in.');

            $hadir->forceFill(['checked_out_at' => now()])->save();
            $token->forceFill(['used_at' => now()])->save();
            $this->catat($hadir, 'check_out', $actor);
            $event = $assignment->event()->firstOrFail();
            $this->audit->record($actor, 'attendance.check_out', Attendance::class, $hadir->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);

            return $hadir->refresh();
        });
    }

    public function manual(Assignment $assignment, User $actor, string $alasan, string $kunci): Attendance
    {
        return DB::transaction(function () use ($assignment, $actor, $alasan, $kunci): Attendance {
            $assignment = $assignment->refresh();
            $shift = $assignment->shift()->firstOrFail();

            $replay = Attendance::where('idempotency_key', $kunci)->first();
            if ($replay instanceof Attendance) {
                abort_unless(
                    (int) $replay->assignment_id === (int) $assignment->id
                        && (int) $replay->shift_id === (int) $shift->id,
                    404,
                    'Token tidak termasuk event ini.'
                );

                return $replay;
            }

            abort_unless(mb_strlen(trim($alasan)) >= 10, 422, 'Alasan pencatatan manual minimal 10 karakter.');
            $this->pastikanJendela($shift);

            abort_unless($assignment->isActive(), 422, 'Assignment tidak aktif.');
            abort_if(
                Attendance::where('assignment_id', $assignment->id)->where('shift_id', $shift->id)->exists(),
                422,
                'Sudah check-in.'
            );

            $hadir = Attendance::unguarded(fn (): Attendance => Attendance::create([
                'assignment_id' => $assignment->id,
                'shift_id' => $shift->id,
                'event_id' => $assignment->event_id,
                'user_id' => $assignment->user_id,
                'checked_in_at' => now(),
                'method' => 'manual',
                'status' => $this->tentukanStatus($shift),
                'idempotency_key' => $kunci,
            ]));

            $this->catat($hadir, 'check_in', $actor);
            $event = $assignment->event()->firstOrFail();
            $this->audit->record($actor, 'attendance.manual', Attendance::class, $hadir->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'new' => ['method' => 'manual', 'reason' => $alasan],
            ]);

            return $hadir->refresh();
        });
    }

    private function cariToken(string $mentah, ?int $eventId): QrToken
    {
        $token = QrToken::where('token_hash', hash('sha256', $mentah))->first();
        abort_if($token === null, 404, 'Token tidak ditemukan.');

        if ($eventId !== null) {
            $assignment = $token->assignment()->firstOrFail();
            abort_unless((int) $assignment->event_id === (int) $eventId, 404, 'Token tidak termasuk event ini.');
        }

        return $token;
    }

    private function pastikanTokenBisaDipakai(QrToken $token): void
    {
        abort_if($token->revoked_at !== null, 422, 'Token sudah dicabut.');
        abort_if($token->used_at !== null, 422, 'Token sudah dipakai.');
        abort_if($token->expires_at->isPast(), 422, 'Token kedaluwarsa.');
    }

    private function pastikanJendela(EventShift $shift): void
    {
        $awal = (clone $shift->start_at)->subMinutes(30);
        $sekarang = now();
        abort_unless(
            $sekarang->greaterThanOrEqualTo($awal) && $sekarang->lessThanOrEqualTo($shift->end_at),
            422,
            'Di luar jendela kehadiran.'
        );
    }

    private function tentukanStatus(EventShift $shift): string
    {
        return now()->greaterThan((clone $shift->start_at)->addMinutes(15)) ? 'late' : 'present';
    }

    private function catat(Attendance $hadir, string $aksi, User $actor): void
    {
        AttendanceLog::create([
            'attendance_id' => $hadir->id,
            'action' => $aksi,
            'actor_id' => $actor->id,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 512),
            'created_at' => now(),
        ]);
    }
}

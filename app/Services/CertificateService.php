<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CertificateService
{
    public const DEFAULT_THRESHOLD = 50;

    public function __construct(private AuditLogService $audit, private AssignmentService $assignments) {}

    public function effectiveThreshold(Event $event): int
    {
        return $event->certificate_min_attendance_pct ?? self::DEFAULT_THRESHOLD;
    }

    public function isEligible(Event $event, User $user): bool
    {
        if (! in_array($event->status, ['completed', 'archived'], true)) {
            return false;
        }
        $penyebut = Assignment::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereIn('status', Assignment::ACTIVE)
            ->whereHas('shift', fn ($q) => $q->whereNull('deleted_at'))
            ->count();
        if ($penyebut === 0) {
            return false;
        }
        $hadir = Attendance::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['present', 'late'])
            ->count();
        if ($hadir < 1) {
            return false;
        }

        return $hadir / $penyebut * 100 >= $this->effectiveThreshold($event);
    }

    /** @return array{issued: array<Certificate>, skipped: int} */
    public function issueBatch(Event $event, User $actor, ?int $ambang = null): array
    {
        abort_unless(in_array($event->status, ['completed', 'archived'], true), 422, 'Sertifikat hanya diterbitkan untuk event yang sudah selesai.');
        if ($ambang !== null) {
            abort_unless($ambang >= 1 && $ambang <= 100, 422, 'Ambang kehadiran harus 1–100.');
        }

        return DB::transaction(function () use ($event, $actor, $ambang): array {
            $terkunci = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($ambang !== null) {
                $terkunci->forceFill(['certificate_min_attendance_pct' => $ambang])->save();
            }
            $tugas = Assignment::where('event_id', $terkunci->id)
                ->whereIn('status', Assignment::ACTIVE)
                ->with('shift')
                ->get();
            foreach ($tugas as $item) {
                if ($item->shift !== null && now()->gte($item->shift->end_at)) {
                    $this->assignments->complete($item, $actor);
                }
            }
            $calonIds = Assignment::where('event_id', $terkunci->id)
                ->whereIn('status', ['completed'])
                ->distinct()
                ->pluck('user_id');
            $diterbitkan = [];
            $dilewati = 0;
            foreach ($calonIds as $userId) {
                $user = User::whereKey($userId)->firstOrFail();
                if (! $this->layakUntukTerbit($terkunci->id, $user)) {
                    $dilewati++;

                    continue;
                }
                if (Certificate::where('event_id', $terkunci->id)->where('user_id', $userId)->exists()) {
                    $dilewati++;

                    continue;
                }
                $diterbitkan[] = $this->terbitkan(Event::whereKey($terkunci->id)->firstOrFail(), $user, $actor);
            }

            return ['issued' => $diterbitkan, 'skipped' => $dilewati];
        });
    }

    public function issueIndividual(Event $event, User $target, User $actor): Certificate
    {
        abort_unless(in_array($event->status, ['completed', 'archived'], true), 422, 'Sertifikat hanya diterbitkan untuk event yang sudah selesai.');
        abort_unless($this->isEligible($event, $target), 422, 'Volunteer belum memenuhi syarat sertifikat.');

        return DB::transaction(function () use ($event, $target, $actor): Certificate {
            abort_if(Certificate::where('event_id', $event->id)->where('user_id', $target->id)->exists(), 422, 'Sertifikat sudah diterbitkan.');

            return $this->terbitkan($event, $target, $actor);
        });
    }

    public function revoke(Certificate $sertifikat, User $actor, string $alasan): Certificate
    {
        return DB::transaction(function () use ($sertifikat, $actor, $alasan): Certificate {
            abort_if($sertifikat->isRevoked(), 422, 'Sertifikat sudah dicabut.');
            abort_unless(mb_strlen(trim($alasan)) >= 10, 422, 'Alasan pencabutan minimal 10 karakter.');
            $sertifikat->forceFill(['revoked_at' => now(), 'revoke_reason' => $alasan])->save();
            $event = $sertifikat->event()->firstOrFail();
            $this->audit->record($actor, 'certificate.revoked', Certificate::class, $sertifikat->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'new' => ['reason' => $alasan],
            ]);

            return $sertifikat->refresh();
        });
    }

    private function layakUntukTerbit(int $eventId, User $user): bool
    {
        $event = Event::whereKey($eventId)->firstOrFail();
        if (! in_array($event->status, ['completed', 'archived'], true)) {
            return false;
        }
        $hadir = Attendance::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->whereIn('status', ['present', 'late'])
            ->count();
        if ($hadir < 1) {
            return false;
        }
        $penyebut = Assignment::where('event_id', $eventId)
            ->where('user_id', $user->id)
            ->whereIn('status', [...Assignment::ACTIVE, 'completed'])
            ->whereHas('shift', fn ($q) => $q->whereNull('deleted_at'))
            ->count();
        if ($penyebut === 0) {
            return false;
        }

        return $hadir / $penyebut * 100 >= $this->effectiveThreshold($event);
    }

    private function terbitkan(Event $event, User $target, User $actor): Certificate
    {
        $registrationId = $target->registrations()->where('event_id', $event->id)->where('status', 'accepted')->value('id');
        abort_if($registrationId === null, 422, 'Tidak ada pendaftaran diterima untuk volunteer ini.');
        $mentah = Str::random(64);
        $sertifikat = Certificate::unguarded(fn (): Certificate => Certificate::create([
            'event_id' => $event->id,
            'user_id' => $target->id,
            'registration_id' => $registrationId,
            'certificate_no' => $this->nomorBaru(),
            'issued_at' => now(),
            'qr_token_hash' => hash('sha256', $mentah),
        ]));
        $this->audit->record($actor, 'certificate.issued', Certificate::class, $sertifikat->id, [
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
        ]);

        return $sertifikat->refresh();
    }

    private function nomorBaru(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $nomor = 'WV-'.now()->format('Y').'-'.strtoupper(Str::random(6));
            if (! Certificate::where('certificate_no', $nomor)->exists()) {
                return $nomor;
            }
        }

        return 'WV-'.now()->format('Y').'-'.strtoupper(Str::random(8));
    }
}

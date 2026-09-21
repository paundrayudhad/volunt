<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\ArtistLiaison;
use App\Models\ArtistNote;
use App\Models\ArtistStatusHistory;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ArtistService
{
    public function __construct(private AuditLogService $audit) {}

    /** @param array{name: string, genre?: ?string, stage?: ?string, scheduled_at?: ?string, duration_minutes?: ?int, performance_order?: ?int, contact_name?: ?string, contact_phone?: ?string, rider_text?: ?string} $data */
    public function create(Event $event, User $actor, array $data): Artist
    {
        $this->validasiJadwal($event, $data);

        return DB::transaction(function () use ($event, $actor, $data): Artist {
            $artis = Artist::unguarded(fn (): Artist => Artist::create([
                'event_id' => $event->id,
                'name' => $data['name'],
                'genre' => $data['genre'] ?? null,
                'stage' => $data['stage'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'performance_order' => $data['performance_order'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'rider_text' => $data['rider_text'] ?? null,
                'status' => 'scheduled',
                'attendance' => 'expected',
            ]));
            $this->audit->record($actor, 'artist.created', Artist::class, $artis->id, [
                'event_id' => $event->id, 'new' => ['name' => $artis->name],
            ]);

            return $artis;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Artist $artis, User $actor, array $data): Artist
    {
        $this->validasiJadwal($artis->event, $data, $artis);

        return DB::transaction(function () use ($artis, $actor, $data): Artist {
            $artis->forceFill(collect($data)->only([
                'name', 'genre', 'stage', 'scheduled_at', 'duration_minutes',
                'performance_order', 'contact_name', 'contact_phone', 'rider_text',
            ])->all())->save();
            $this->audit->record($actor, 'artist.updated', Artist::class, $artis->id, [
                'event_id' => $artis->event_id,
            ]);

            return $artis->refresh();
        });
    }

    public function transition(Artist $artis, User $actor, string $tujuan, ?string $catatan = null): Artist
    {
        $sah = Artist::NEXT[$artis->status] ?? [];
        abort_unless(in_array($tujuan, $sah, true), 422, 'Tahap berikutnya yang sah: '.($sah === [] ? 'tidak ada.' : implode(', ', $sah).'.'));

        return $this->catat($artis, $actor, ['status' => $tujuan], 'artist.transitioned', $catatan);
    }

    public function markAttendance(Artist $artis, User $actor, string $kehadiran): Artist
    {
        abort_unless(in_array($kehadiran, Artist::ATTENDANCES, true), 422, 'Status kehadiran tidak dikenal.');
        abort_unless($kehadiran !== $artis->attendance, 422, 'Status kehadiran sudah '.$artis->attendance.'.');

        return $this->catat($artis, $actor, ['attendance' => $kehadiran], 'artist.attendance_marked');
    }

    public function cancel(Artist $artis, User $actor, string $alasan): Artist
    {
        abort_unless(in_array($artis->status, ['scheduled', 'soundcheck', 'performing'], true), 422, 'Hanya artis yang belum tampil yang dapat dibatalkan.');
        abort_unless(mb_strlen(trim($alasan)) >= 10, 422, 'Alasan pembatalan minimal 10 karakter.');

        return $this->catat($artis, $actor, ['status' => 'cancelled'], 'artist.cancelled', $alasan);
    }

    public function assignLiaison(Artist $artis, User $actor, User $lo): ArtistLiaison
    {
        abort_unless($this->volunteerEvent($artis->event_id, $lo->id), 422, 'Hanya volunteer event ini yang dapat menjadi LO.');
        abort_if($artis->liaisons()->where('user_id', $lo->id)->exists(), 422, 'Volunteer sudah menjadi LO artis ini.');
        $this->validasiDampingan($artis, $lo);

        return DB::transaction(function () use ($artis, $actor, $lo): ArtistLiaison {
            $liaison = ArtistLiaison::unguarded(fn (): ArtistLiaison => ArtistLiaison::create([
                'artist_id' => $artis->id,
                'user_id' => $lo->id,
            ]));
            $this->audit->record($actor, 'artist.liaison_assigned', ArtistLiaison::class, $liaison->id, [
                'event_id' => $artis->event_id,
                'new' => ['artist_id' => $artis->id, 'user_id' => $lo->id],
            ]);

            return $liaison;
        });
    }

    public function releaseLiaison(ArtistLiaison $liaison, User $actor): void
    {
        DB::transaction(function () use ($liaison, $actor): void {
            $masihAktif = ! $liaison->trashed();
            $liaison->delete();
            $this->audit->record($actor, 'artist.liaison_released', ArtistLiaison::class, $liaison->id, [
                'event_id' => $liaison->artist->event_id,
                'old' => ['aktif' => $masihAktif],
                'new' => ['aktif' => false],
            ]);
        });
    }

    public function addNote(Artist $artis, User $penulis, string $isi): ArtistNote
    {
        $tubuh = trim($isi);
        abort_unless($tubuh !== '', 422, 'Catatan tidak boleh kosong.');
        abort_if(mb_strlen($tubuh) > 2000, 422, 'Catatan maksimal 2000 karakter.');

        return DB::transaction(function () use ($artis, $penulis, $tubuh): ArtistNote {
            $catatan = ArtistNote::unguarded(fn (): ArtistNote => $artis->notes()->create([
                'author_id' => $penulis->id,
                'body' => $tubuh,
            ]));
            $this->audit->record($penulis, 'artist.note_added', Artist::class, $artis->id, [
                'event_id' => $artis->event_id,
                'new' => ['note_id' => $catatan->id],
            ]);

            return $catatan;
        });
    }

    public function toggleRider(Artist $artis, User $actor, bool $terpenuhi): Artist
    {
        return DB::transaction(function () use ($artis, $actor, $terpenuhi): Artist {
            $dari = $artis->rider_fulfilled;
            $artis->forceFill(['rider_fulfilled' => $terpenuhi])->save();
            $this->audit->record($actor, 'artist.rider_toggled', Artist::class, $artis->id, [
                'event_id' => $artis->event_id,
                'old' => ['rider_fulfilled' => $dari],
                'new' => ['rider_fulfilled' => $terpenuhi],
            ]);

            return $artis->refresh();
        });
    }

    /** @param array{status?: string, attendance?: string} $perubahan */
    private function catat(Artist $artis, User $actor, array $perubahan, string $aksi, ?string $catatan = null): Artist
    {
        return DB::transaction(function () use ($artis, $actor, $perubahan, $aksi, $catatan): Artist {
            $dariStatus = $artis->status;
            $dariAttendance = $artis->attendance;
            $artis->forceFill($perubahan)->save();
            ArtistStatusHistory::unguarded(fn (): mixed => $artis->histories()->create([
                'from_status' => array_key_exists('status', $perubahan) ? $dariStatus : null,
                'to_status' => $perubahan['status'] ?? null,
                'from_attendance' => array_key_exists('attendance', $perubahan) ? $dariAttendance : null,
                'to_attendance' => $perubahan['attendance'] ?? null,
                'actor_id' => $actor->id,
                'note' => $catatan,
            ]));
            $this->audit->record($actor, $aksi, Artist::class, $artis->id, [
                'event_id' => $artis->event_id,
                'old' => ['status' => $dariStatus, 'attendance' => $dariAttendance],
                'new' => $perubahan,
            ]);

            return $artis->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function validasiJadwal(Event $event, array $data, ?Artist $kecuali = null): void
    {
        if ($kecuali === null || array_key_exists('name', $data)) {
            abort_unless(
                isset($data['name']) && is_string($data['name']) && mb_strlen(trim($data['name'])) > 0,
                422, 'Nama artis wajib diisi.'
            );
        }
        $namaAkhir = trim((string) ($data['name'] ?? $kecuali->name ?? ''));

        $mentah = array_key_exists('scheduled_at', $data) ? $data['scheduled_at'] : $kecuali?->scheduled_at;
        $jadwal = $mentah === null ? null : $this->parseJadwal($mentah);

        $durasiMentah = array_key_exists('duration_minutes', $data) ? $data['duration_minutes'] : $kecuali?->duration_minutes;
        if ($durasiMentah !== null) {
            abort_unless(is_numeric($durasiMentah) && $durasiMentah >= 15 && $durasiMentah <= 240, 422, 'Durasi 15–240 menit.');
        }
        $durasi = (int) ($durasiMentah ?? Artist::DEFAULT_DURATION);

        if ($jadwal === null) {
            return;
        }
        abort_unless($jadwal->between($event->start_at, $event->end_at), 422, 'Jadwal harus dalam rentang event.');

        $mentahPanggung = array_key_exists('stage', $data) ? $data['stage'] : $kecuali?->stage;
        $panggung = is_string($mentahPanggung) && trim($mentahPanggung) !== '' ? trim($mentahPanggung) : null;
        $akhir = $jadwal->copy()->addMinutes($durasi);

        // Kandidat kecil per event: filter overlap + stage di PHP, hindari SQL datetime kompleks.
        $kandidat = Artist::where('event_id', $event->id)
            ->whereNotNull('scheduled_at')
            ->where('status', '!=', 'cancelled')
            ->whereKeyNot($kecuali !== null ? $kecuali->id : 0)
            ->get();

        foreach ($kandidat as $lain) {
            $mulaiLain = $lain->scheduled_at;
            $akhirLain = $lain->endsAt();
            if ($akhirLain === null) {
                continue;
            }
            if (! $this->rentangOverlap($jadwal, $akhir, $mulaiLain, $akhirLain)) {
                continue;
            }
            if ($panggung !== null && $lain->stage !== null && trim((string) $lain->stage) === $panggung) {
                abort(422, "Bentrok dengan {$lain->name} di panggung yang sama {$mulaiLain->format('H.i')}–{$akhirLain->format('H.i')}.");
            }
            if (trim((string) $lain->name) === $namaAkhir) {
                abort(422, "Jadwal {$namaAkhir} tumpang tindih dengan jadwal lain {$mulaiLain->format('H.i')}–{$akhirLain->format('H.i')}.");
            }
        }
    }

    private function parseJadwal(mixed $mentah): Carbon
    {
        try {
            return $mentah instanceof CarbonInterface
                ? Carbon::parse($mentah->toDateTimeString())
                : Carbon::parse($mentah);
        } catch (\Throwable) {
            abort(422, 'Format jadwal tidak valid.');
        }
    }

    private function rentangOverlap(CarbonInterface $a1, CarbonInterface $a2, CarbonInterface $b1, CarbonInterface $b2): bool
    {
        return $a1->lt($b2) && $a2->gt($b1);
    }

    /**
     * Mekanisme sama persis dengan penentu "event yang ia ikuti" di
     * Volunteer\IncidentController::diikuti(): registrations dengan status accepted.
     */
    private function volunteerEvent(int $eventId, int $userId): bool
    {
        return Registration::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->exists();
    }

    private function validasiDampingan(Artist $artis, User $lo): void
    {
        if ($artis->scheduled_at === null) {
            return;
        }
        $mulai = $artis->scheduled_at->copy();
        $akhir = $mulai->copy()->addMinutes($artis->duration_minutes ?? Artist::DEFAULT_DURATION);

        $dampingan = ArtistLiaison::where('user_id', $lo->id)
            ->whereHas('artist', fn ($query) => $query
                ->where('event_id', $artis->event_id)
                ->whereKeyNot($artis->id)
                ->whereNotNull('scheduled_at')
                ->where('status', '!=', 'cancelled'))
            ->with('artist')
            ->get();

        foreach ($dampingan as $row) {
            $lain = $row->artist;
            $akhirLain = $lain->endsAt();
            if ($akhirLain === null) {
                continue;
            }
            if ($this->rentangOverlap($mulai, $akhir, $lain->scheduled_at, $akhirLain)) {
                abort(422, "Volunteer sudah mendampingi {$lain->name} pada jam yang sama.");
            }
        }
    }
}

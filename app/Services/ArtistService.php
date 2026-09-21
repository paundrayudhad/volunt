<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\ArtistStatusHistory;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
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
        $this->validasiJadwal($artis->event, $data);

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
    private function validasiJadwal(Event $event, array $data): void
    {
        // Task 3 mengisi cek konflik di sini. Saat ini: rentang event + durasi saja.
        if (array_key_exists('scheduled_at', $data) && $data['scheduled_at'] !== null) {
            $jadwal = Carbon::parse($data['scheduled_at']);
            abort_unless($jadwal->between($event->start_at, $event->end_at), 422, 'Jadwal harus dalam rentang event.');
        }
        if (array_key_exists('duration_minutes', $data) && $data['duration_minutes'] !== null) {
            abort_unless($data['duration_minutes'] >= 15 && $data['duration_minutes'] <= 240, 422, 'Durasi 15–240 menit.');
        }
    }
}

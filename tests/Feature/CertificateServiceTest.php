<?php

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\CertificateService;
use App\Services\EventService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @return array{org: Organization, owner: User, event: Event, divisi: EventDivision, role: EventRole} */
function sertTesSetup(): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $divisi = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $divisi->id,
        'quota' => 50,
        'accepted_count' => 0,
    ]);

    return [
        'org' => $org,
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'divisi' => $divisi->refresh(),
        'role' => $role->refresh(),
    ];
}

function sertTesSelesaikanEvent(array $s): Event
{
    $svc = app(EventService::class);
    $event = $s['event']->refresh();
    foreach (['published', 'registration_open', 'registration_closed', 'ongoing', 'completed'] as $berikut) {
        $event = $svc->transitionTo($event, $berikut, $s['owner']);
    }

    return $event->refresh();
}

function sertTesRegistrasi(array $s, ?User $relawan = null): Registration
{
    return Registration::factory()->create([
        'user_id' => ($relawan ?? User::factory()->create())->id,
        'event_id' => $s['event']->id,
        'role_id' => $s['role']->id,
        'status' => 'accepted',
    ]);
}

function sertTesShift(array $s, Carbon $mulai, Carbon $selesai, ?int $kapasitas = 50): EventShift
{
    return EventShift::factory()->create([
        'event_id' => $s['event']->id,
        'division_id' => $s['divisi']->id,
        'role_id' => $s['role']->id,
        'start_at' => $mulai,
        'end_at' => $selesai,
        'capacity' => $kapasitas,
        'filled_count' => 0,
    ]);
}

function sertTesTugaskan(array $s, Registration $reg, EventShift $shift): Assignment
{
    $tugas = app(AssignmentService::class)->assign($reg, $shift, $s['owner']);
    app(AssignmentService::class)->confirm($tugas->refresh(), $s['owner']);

    return $tugas->refresh();
}

function sertTesKonfirmasi(array $s, Assignment $tugas): Assignment
{
    return $tugas->refresh();
}

function sertTesHadir(Assignment $tugas, string $status = 'present'): Attendance
{
    return Attendance::unguarded(fn (): Attendance => Attendance::create([
        'assignment_id' => $tugas->id,
        'shift_id' => $tugas->shift_id,
        'event_id' => $tugas->event_id,
        'user_id' => $tugas->user_id,
        'checked_in_at' => now(),
        'method' => 'manual',
        'status' => $status,
        'idempotency_key' => (string) Str::uuid(),
    ]));
}

/**
 * Satu user hanya boleh punya satu registration aktif per event
 * (unique registrations_user_event_active_uniq). Helper ini memberi satu
 * registration 'accepted' per user per event, lalu menambah assignment aktif
 * lewat tugasan tambahan yang memakai registration lain berstatus terminal
 * ('withdrawn') — registration terminal tetap valid sebagai FK assignment
 * namun tidak dihitung sebagai pendaftaran diterima.
 *
 * @return array{registrasi: Registration, tugas: list<Assignment>}
 */
function sertTesPaketRasio(array $s, User $user, int $jumlahAktif): array
{
    $registrasi = Registration::factory()->create([
        'user_id' => $user->id,
        'event_id' => $s['event']->id,
        'role_id' => $s['role']->id,
        'status' => 'accepted',
    ]);
    $tugas = [];
    for ($i = 0; $i < $jumlahAktif; $i++) {
        $regSamping = Registration::factory()->create([
            'user_id' => $user->id,
            'event_id' => $s['event']->id,
            'role_id' => $s['role']->id,
            'status' => 'withdrawn',
        ]);
        $shift = sertTesShift($s, now()->subDays(6 - $i * 2), now()->subDays(5 - $i * 2));
        $tugas[] = Assignment::unguarded(fn (): Assignment => Assignment::create([
            'registration_id' => $regSamping->id,
            'user_id' => $user->id,
            'event_id' => $s['event']->id,
            'division_id' => $shift->division_id,
            'role_id' => $shift->role_id,
            'shift_id' => $shift->id,
            'status' => 'confirmed',
        ]));
    }

    return ['registrasi' => $registrasi, 'tugas' => $tugas];
}

/**
 * Assignment mentah (tanpa service) untuk menyusun penyebut rasio.
 * Dipakai bila test sudah punya registration 'accepted' sendiri, sehingga
 * registration samping memakai status terminal 'withdrawn' agar tidak
 * melanggar unique aktif.
 */
function sertTesTugasMentah(array $s, User $user, EventShift $shift, string $statusTugas = 'confirmed'): Assignment
{
    $reg = Registration::factory()->create([
        'user_id' => $user->id,
        'event_id' => $s['event']->id,
        'role_id' => $shift->role_id ?? $s['role']->id,
        'status' => 'withdrawn',
    ]);

    return Assignment::unguarded(fn (): Assignment => Assignment::create([
        'registration_id' => $reg->id,
        'user_id' => $user->id,
        'event_id' => $s['event']->id,
        'division_id' => $shift->division_id,
        'role_id' => $shift->role_id,
        'shift_id' => $shift->id,
        'status' => $statusTugas,
    ]));
}

it('sertTesCompleteMenutupAktifShiftLewat', function (): void {
    $s = sertTesSetup();
    sertTesSelesaikanEvent($s);
    $shift = sertTesShift($s, now()->subDays(3), now()->subDays(2));
    $tugas = sertTesKonfirmasi($s, sertTesTugaskan($s, sertTesRegistrasi($s), $shift));

    $hasil = app(AssignmentService::class)->complete($tugas, $s['owner']);

    expect($hasil->status)->toBe('completed')
        ->and($hasil->histories()->where('to_status', 'completed')->count())->toBe(1)
        ->and($hasil->histories()->where('to_status', 'completed')->first()->from_status)->toBe('confirmed');
});

it('sertTesCompleteTolakShiftBelumLewat', function (): void {
    $s = sertTesSetup();
    sertTesSelesaikanEvent($s);
    $shift = sertTesShift($s, now()->addDay(), now()->addDays(2));
    $tugas = sertTesKonfirmasi($s, sertTesTugaskan($s, sertTesRegistrasi($s), $shift));

    try {
        app(AssignmentService::class)->complete($tugas, $s['owner']);
        $this->fail('Seharusnya menolak complete untuk shift yang belum selesai.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Shift belum selesai.')
            ->and($tugas->refresh()->status)->toBe('confirmed');
    }
});

it('sertTesTidakLayakEventBelumSelesai', function (): void {
    $s = sertTesSetup();
    $relawan = User::factory()->create();
    $shift = sertTesShift($s, now()->subDays(3), now()->subDays(2));
    $tugas = sertTesKonfirmasi($s, sertTesTugaskan($s, sertTesRegistrasi($s, $relawan), $shift));
    sertTesHadir($tugas);

    expect(app(CertificateService::class)->isEligible($s['event']->refresh(), $relawan))->toBeFalse();
});

it('sertTesTidakLayakTanpaAssignmentAktif', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $shift = sertTesShift($s, now()->subDays(3), now()->subDays(2));
    $tugas = sertTesTugaskan($s, sertTesRegistrasi($s, $relawan), $shift);
    app(AssignmentService::class)->cancel($tugas, $s['owner'], 'Volunteer berhalangan hadir.');
    sertTesHadir($tugas);

    expect(app(CertificateService::class)->isEligible($event->refresh(), $relawan))->toBeFalse();
});

it('sertTesTidakLayakDiBawahAmbang', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $paket = sertTesPaketRasio($s, $relawan, 3);
    sertTesHadir($paket['tugas'][0]);

    expect(app(CertificateService::class)->isEligible($event->refresh(), $relawan))->toBeFalse();
});

it('sertTesLayakTepatAmbang', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $paket = sertTesPaketRasio($s, $relawan, 2);
    sertTesHadir($paket['tugas'][0]);

    expect(app(CertificateService::class)->isEligible($event->refresh(), $relawan))->toBeTrue();
});

it('sertTesAmbangPerEventDihormati', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $event->forceFill(['certificate_min_attendance_pct' => 100])->save();
    $relawan = User::factory()->create();
    $svc = app(CertificateService::class);
    $paket = sertTesPaketRasio($s, $relawan, 2);
    sertTesHadir($paket['tugas'][0]);

    expect($svc->effectiveThreshold($event->refresh()))->toBe(100)
        ->and($svc->isEligible($event->refresh(), $relawan))->toBeFalse();

    $s2 = sertTesSetup();
    $penuh = sertTesSelesaikanEvent($s2);
    $penuh->forceFill(['certificate_min_attendance_pct' => 100])->save();
    $lengkap = User::factory()->create();
    $paketPenuh = sertTesPaketRasio($s2, $lengkap, 2);
    sertTesHadir($paketPenuh['tugas'][0]);
    sertTesHadir($paketPenuh['tugas'][1]);

    expect($svc->isEligible($penuh->refresh(), $lengkap))->toBeTrue();
});

it('sertTesShiftSoftDeleteTakMasukPenyebut', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $paket = sertTesPaketRasio($s, $relawan, 2);
    sertTesHadir($paket['tugas'][0]);
    $paket['tugas'][1]->shift()->firstOrFail()->delete();

    expect(app(CertificateService::class)->isEligible($event->refresh(), $relawan))->toBeTrue();
});

it('sertTesCancelledTakMasukPenyebut', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $paket = sertTesPaketRasio($s, $relawan, 2);
    sertTesHadir($paket['tugas'][0]);
    $paket['tugas'][1]->forceFill(['status' => 'cancelled'])->save();

    expect(app(CertificateService::class)->isEligible($event->refresh(), $relawan))->toBeTrue();
});

it('sertTesBatchTerbitDanTutup', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawanA = User::factory()->create();
    $relawanB = User::factory()->create();
    $relawanC = User::factory()->create();
    $tugasA = sertTesTugaskan($s, sertTesRegistrasi($s, $relawanA), sertTesShift($s, now()->subDays(4), now()->subDays(3)));
    $tugasB = sertTesTugaskan($s, sertTesRegistrasi($s, $relawanB), sertTesShift($s, now()->subDays(4), now()->subDays(3)));
    $tugasC = sertTesTugaskan($s, sertTesRegistrasi($s, $relawanC), sertTesShift($s, now()->subDays(4), now()->subDays(3)));
    sertTesHadir($tugasA);
    sertTesHadir($tugasB);

    $layakA = app(CertificateService::class)->isEligible($event->refresh(), $relawanA);
    $layakB = app(CertificateService::class)->isEligible($event->refresh(), $relawanB);
    expect($layakA)->toBeTrue()->and($layakB)->toBeTrue();

    $hasil = app(CertificateService::class)->issueBatch($event->refresh(), $s['owner']);

    expect($hasil['issued'])->toHaveCount(2)
        ->and($hasil['skipped'])->toBe(1)
        ->and($tugasA->refresh()->status)->toBe('completed')
        ->and($tugasB->refresh()->status)->toBe('completed')
        ->and($tugasC->refresh()->status)->toBe('completed')
        ->and(Certificate::where('event_id', $event->id)->count())->toBe(2);
    foreach ($hasil['issued'] as $sertifikat) {
        expect($sertifikat->certificate_no)->toStartWith('WV-'.now()->format('Y').'-');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'certificate.issued',
            'resource_type' => Certificate::class,
            'resource_id' => (string) $sertifikat->id,
        ]);
    }
});

it('sertTesBatchShiftBelumLewatTakDitutup', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $tugas = sertTesTugaskan($s, sertTesRegistrasi($s, $relawan), sertTesShift($s, now()->addDay(), now()->addDays(2)));

    $hasil = app(CertificateService::class)->issueBatch($event->refresh(), $s['owner']);

    expect($hasil['issued'])->toHaveCount(0)
        ->and($hasil['skipped'])->toBe(0)
        ->and($tugas->refresh()->status)->toBe('confirmed')
        ->and(Certificate::where('event_id', $event->id)->count())->toBe(0);
});

it('sertTesBatchRerunIdempoten', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $tugas = sertTesTugaskan($s, sertTesRegistrasi($s, $relawan), sertTesShift($s, now()->subDays(4), now()->subDays(3)));
    sertTesHadir($tugas);
    $svc = app(CertificateService::class);

    $pertama = $svc->issueBatch($event->refresh(), $s['owner']);
    $kedua = $svc->issueBatch($event->refresh(), $s['owner']);

    expect($pertama['issued'])->toHaveCount(1)
        ->and($kedua['issued'])->toHaveCount(0)
        ->and($kedua['skipped'])->toBe(1)
        ->and(Certificate::where('event_id', $event->id)->count())->toBe(1);
});

it('sertTesBatchTolakEventOngoing', function (): void {
    $s = sertTesSetup();
    $svc = app(EventService::class);
    $event = $s['event']->refresh();
    foreach (['published', 'registration_open', 'registration_closed', 'ongoing'] as $berikut) {
        $event = $svc->transitionTo($event, $berikut, $s['owner']);
    }

    try {
        app(CertificateService::class)->issueBatch($event->refresh(), $s['owner']);
        $this->fail('Seharusnya menolak batch untuk event yang belum selesai.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Sertifikat hanya diterbitkan untuk event yang sudah selesai.');
    }
});

it('sertTesBatchSimpanAmbang', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $paket = sertTesPaketRasio($s, $relawan, 2);
    sertTesHadir($paket['tugas'][0]);

    $hasil = app(CertificateService::class)->issueBatch($event->refresh(), $s['owner'], 100);

    expect($hasil['issued'])->toHaveCount(0)
        ->and($hasil['skipped'])->toBe(1)
        ->and($event->refresh()->certificate_min_attendance_pct)->toBe(100);
});

it('sertTesRevokeDanHormati', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $tugas = sertTesTugaskan($s, sertTesRegistrasi($s, $relawan), sertTesShift($s, now()->subDays(4), now()->subDays(3)));
    sertTesHadir($tugas);
    $svc = app(CertificateService::class);
    $terbit = $svc->issueBatch($event->refresh(), $s['owner']);
    $sertifikat = $terbit['issued'][0];

    $cabut = $svc->revoke($sertifikat, $s['owner'], 'Data kehadiran tidak valid.');

    expect($cabut->isRevoked())->toBeTrue()
        ->and($cabut->revoke_reason)->toBe('Data kehadiran tidak valid.')
        ->and($tugas->refresh()->status)->toBe('completed');
    $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.revoked']);

    $ulang = $svc->issueBatch($event->refresh(), $s['owner']);

    expect($ulang['issued'])->toHaveCount(0)
        ->and($ulang['skipped'])->toBe(1)
        ->and(Certificate::where('event_id', $event->id)->count())->toBe(1);
});

it('sertTesRevokeAlasanPendek', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $tugas = sertTesTugaskan($s, sertTesRegistrasi($s, $relawan), sertTesShift($s, now()->subDays(4), now()->subDays(3)));
    sertTesHadir($tugas);
    $sertifikat = app(CertificateService::class)->issueBatch($event->refresh(), $s['owner'])['issued'][0];

    try {
        app(CertificateService::class)->revoke($sertifikat, $s['owner'], 'Terlalu.');
        $this->fail('Seharusnya menolak alasan pencabutan yang pendek.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Alasan pencabutan minimal 10 karakter.')
            ->and($sertifikat->refresh()->isRevoked())->toBeFalse();
    }
});

it('sertTesRevokeGanda', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $tugas = sertTesTugaskan($s, sertTesRegistrasi($s, $relawan), sertTesShift($s, now()->subDays(4), now()->subDays(3)));
    sertTesHadir($tugas);
    $svc = app(CertificateService::class);
    $sertifikat = $svc->issueBatch($event->refresh(), $s['owner'])['issued'][0];
    $svc->revoke($sertifikat->refresh(), $s['owner'], 'Data kehadiran tidak valid.');

    try {
        $svc->revoke($sertifikat->refresh(), $s['owner'], 'Alasan kedua yang panjang.');
        $this->fail('Seharusnya menolak pencabutan kedua.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Sertifikat sudah dicabut.');
    }
});

it('sertTesIndividualTolakBilaPernahAda', function (): void {
    $s = sertTesSetup();
    $event = sertTesSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $tugas = sertTesTugaskan($s, sertTesRegistrasi($s, $relawan), sertTesShift($s, now()->subDays(4), now()->subDays(3)));
    sertTesHadir($tugas);
    $svc = app(CertificateService::class);

    $terbit = $svc->issueIndividual($event->refresh(), $relawan, $s['owner']);
    expect($terbit->user_id)->toBe($relawan->id);

    try {
        $svc->issueIndividual($event->refresh(), $relawan, $s['owner']);
        $this->fail('Seharusnya menolak penerbitan ganda.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Sertifikat sudah diterbitkan.');
    }

    $svc->revoke($terbit->refresh(), $s['owner'], 'Salah input nama volunteer.');

    try {
        $svc->issueIndividual($event->refresh(), $relawan, $s['owner']);
        $this->fail('Revoke bersifat final: tidak boleh terbit baru setelah dicabut.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Sertifikat sudah diterbitkan.')
            ->and(Certificate::where('event_id', $event->id)->where('user_id', $relawan->id)->count())->toBe(1);
    }
});

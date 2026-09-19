<?php

use App\Exceptions\ShiftFullException;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\AssignmentService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @return array{org: Organization, owner: User, event: Event, divisi: EventDivision, role: EventRole} */
function assignTesSetup(): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $divisi = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $divisi->id,
        'quota' => 10,
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

function assignTesRegistrasiDiterima(array $s, ?User $relawan = null): Registration
{
    return Registration::factory()->create([
        'user_id' => ($relawan ?? User::factory()->create())->id,
        'event_id' => $s['event']->id,
        'role_id' => $s['role']->id,
        'status' => 'accepted',
    ]);
}

function assignTesShift(array $s, int $jamMulai, int $jamSelesai, ?int $kapasitas = 2): EventShift
{
    $dasar = now()->addDays(5)->startOfDay();

    return EventShift::factory()->create([
        'event_id' => $s['event']->id,
        'division_id' => $s['divisi']->id,
        'role_id' => $s['role']->id,
        'start_at' => (clone $dasar)->setTime($jamMulai, 0),
        'end_at' => (clone $dasar)->setTime($jamSelesai, 0),
        'capacity' => $kapasitas,
        'filled_count' => 0,
    ]);
}

it('assign valid membuat assignment assigned beserta kuota dan riwayat', function (): void {
    $s = assignTesSetup();
    $reg = assignTesRegistrasiDiterima($s);
    $shift = assignTesShift($s, 10, 12);

    $hasil = app(AssignmentService::class)->assign($reg, $shift, $s['owner']);

    expect($hasil->status)->toBe('assigned')
        ->and($hasil->registration_id)->toBe($reg->id)
        ->and($hasil->user_id)->toBe($reg->user_id)
        ->and($hasil->shift_id)->toBe($shift->id)
        ->and($shift->refresh()->filled_count)->toBe(1)
        ->and($hasil->histories()->count())->toBe(1)
        ->and($hasil->histories()->first()->from_status)->toBeNull()
        ->and($hasil->histories()->first()->to_status)->toBe('assigned')
        ->and($hasil->histories()->first()->changed_by)->toBe($s['owner']->id);
});

it('assign ke shift penuh melempar ShiftFullException 422 dan counter tetap', function (): void {
    $s = assignTesSetup();
    $svc = app(AssignmentService::class);
    $shift = assignTesShift($s, 10, 12, 1);
    $svc->assign(assignTesRegistrasiDiterima($s), $shift, $s['owner']);
    $penuh = assignTesRegistrasiDiterima($s);

    try {
        $svc->assign($penuh, $shift, $s['owner']);
        $this->fail('Seharusnya menolak shift yang penuh.');
    } catch (ShiftFullException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Kuota shift sudah penuh.')
            ->and($shift->refresh()->filled_count)->toBe(1)
            ->and(Assignment::where('registration_id', $penuh->id)->exists())->toBeFalse();
    }
});

it('assign ke shift tanpa batas kapasitas selalu berhasil', function (): void {
    $s = assignTesSetup();
    $svc = app(AssignmentService::class);
    $shift = assignTesShift($s, 10, 12, null);

    $pertama = $svc->assign(assignTesRegistrasiDiterima($s), $shift, $s['owner']);
    $kedua = $svc->assign(assignTesRegistrasiDiterima($s), $shift, $s['owner']);

    expect($pertama->status)->toBe('assigned')
        ->and($kedua->status)->toBe('assigned')
        ->and($shift->refresh()->filled_count)->toBe(2);
});

it('assign ke shift yang overlap ditolak 422', function (): void {
    $s = assignTesSetup();
    $svc = app(AssignmentService::class);
    $relawan = User::factory()->create();
    $shiftA = assignTesShift($s, 10, 12);
    // DB membatasi satu registration aktif per user per event; overlap
    // bermakna bila volunteer yang sama diterima di dua event berbeda
    // namun shift-nya overlap waktu.
    $lain = assignTesSetup();
    $regA = Registration::factory()->create([
        'user_id' => $relawan->id,
        'event_id' => $s['event']->id,
        'role_id' => $s['role']->id,
        'status' => 'accepted',
    ]);
    $regB = Registration::factory()->create([
        'user_id' => $relawan->id,
        'event_id' => $lain['event']->id,
        'role_id' => $lain['role']->id,
        'status' => 'accepted',
    ]);
    $shiftB = assignTesShift($lain, 11, 13);
    $svc->assign($regA, $shiftA, $s['owner']);

    try {
        $svc->assign($regB, $shiftB, $lain['owner']);
        $this->fail('Seharusnya menolak jadwal yang bentrok.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Jadwal bentrok dengan shift lain.')
            ->and($shiftB->refresh()->filled_count)->toBe(0);
    }
});

it('assign registration pending ditolak 422', function (): void {
    $s = assignTesSetup();
    $reg = Registration::factory()->create([
        'user_id' => User::factory()->create()->id,
        'event_id' => $s['event']->id,
        'role_id' => $s['role']->id,
        'status' => 'pending',
    ]);
    $shift = assignTesShift($s, 10, 12);

    try {
        app(AssignmentService::class)->assign($reg, $shift, $s['owner']);
        $this->fail('Seharusnya menolak registration yang belum diterima.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($shift->refresh()->filled_count)->toBe(0);
    }
});

it('assign ke shift beda event ditolak 422', function (): void {
    $s = assignTesSetup();
    $lain = assignTesSetup();
    $reg = assignTesRegistrasiDiterima($s);
    $shiftAsing = assignTesShift($lain, 10, 12);

    try {
        app(AssignmentService::class)->assign($reg, $shiftAsing, $s['owner']);
        $this->fail('Seharusnya menolak shift beda event.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($shiftAsing->refresh()->filled_count)->toBe(0);
    }
});

it('confirm mengubah status menjadi confirmed', function (): void {
    $s = assignTesSetup();
    $svc = app(AssignmentService::class);
    $assignment = $svc->assign(assignTesRegistrasiDiterima($s), assignTesShift($s, 10, 12), $s['owner']);

    $hasil = $svc->confirm($assignment, $s['owner']);

    expect($hasil->status)->toBe('confirmed')
        ->and($hasil->histories()->where('to_status', 'confirmed')->count())->toBe(1);
});

it('reassign memindah shift melepas kuota lama dan mencatat riwayat', function (): void {
    $s = assignTesSetup();
    $svc = app(AssignmentService::class);
    $shiftLama = assignTesShift($s, 10, 12);
    $shiftBaru = assignTesShift($s, 13, 15);
    $assignment = $svc->assign(assignTesRegistrasiDiterima($s), $shiftLama, $s['owner']);

    $hasil = $svc->reassign($assignment, $shiftBaru, $s['owner']);
    $riwayat = $hasil->histories()->where('to_status', 'reassigned')->first();

    expect($hasil->status)->toBe('reassigned')
        ->and($hasil->shift_id)->toBe($shiftBaru->id)
        ->and($shiftLama->refresh()->filled_count)->toBe(0)
        ->and($shiftBaru->refresh()->filled_count)->toBe(1)
        ->and($riwayat->from_status)->toBe('assigned')
        ->and($riwayat->note)->toContain((string) $shiftLama->id)
        ->and($riwayat->note)->toContain((string) $shiftBaru->id);
});

it('cancel melepas kuota shift', function (): void {
    $s = assignTesSetup();
    $svc = app(AssignmentService::class);
    $shift = assignTesShift($s, 10, 12);
    $assignment = $svc->assign(assignTesRegistrasiDiterima($s), $shift, $s['owner']);
    expect($shift->refresh()->filled_count)->toBe(1);

    $hasil = $svc->cancel($assignment, $s['owner'], 'Volunteer berhalangan hadir.');

    expect($hasil->status)->toBe('cancelled')
        ->and($shift->refresh()->filled_count)->toBe(0);
});

it('batal lalu tugaskan ulang registration yang sama berhasil', function (): void {
    $s = assignTesSetup();
    $svc = app(AssignmentService::class);
    $shift = assignTesShift($s, 10, 12);
    $reg = assignTesRegistrasiDiterima($s);
    $lama = $svc->assign($reg, $shift, $s['owner']);
    $svc->cancel($lama, $s['owner'], 'Volunteer berhalangan hadir.');
    expect($shift->refresh()->filled_count)->toBe(0);

    $baru = $svc->assign($reg, $shift, $s['owner']);

    expect($baru->id)->not->toBe($lama->id)
        ->and($baru->status)->toBe('assigned')
        ->and($baru->registration_id)->toBe($reg->id)
        ->and($shift->refresh()->filled_count)->toBe(1);
});

it('riwayat assignment yang dibatalkan tetap tersimpan setelah tugaskan ulang', function (): void {
    $s = assignTesSetup();
    $svc = app(AssignmentService::class);
    $shift = assignTesShift($s, 10, 12);
    $reg = assignTesRegistrasiDiterima($s);
    $lama = $svc->assign($reg, $shift, $s['owner']);
    $svc->cancel($lama, $s['owner'], 'Volunteer berhalangan hadir.');

    $svc->assign($reg, $shift, $s['owner']);

    $lama = $lama->refresh();
    expect($lama->status)->toBe('cancelled')
        ->and($lama->histories()->where('to_status', 'cancelled')->count())->toBe(1)
        ->and(Assignment::where('registration_id', $reg->id)->count())->toBe(2);
});

it('bulkAssign memproses tiga registration sekaligus', function (): void {
    $s = assignTesSetup();
    $shift = assignTesShift($s, 10, 12, 5);
    $ids = [
        assignTesRegistrasiDiterima($s)->id,
        assignTesRegistrasiDiterima($s)->id,
        assignTesRegistrasiDiterima($s)->id,
    ];

    $hasil = app(AssignmentService::class)->bulkAssign($s['event'], $ids, $shift, $s['owner']);

    expect($hasil)->toHaveCount(3)
        ->and($shift->refresh()->filled_count)->toBe(3)
        ->and(Assignment::where('event_id', $s['event']->id)->where('status', 'assigned')->count())->toBe(3);
});

it('bulkAssign dengan id lintas event gagal 404 dan rollback penuh', function (): void {
    $s = assignTesSetup();
    $lain = assignTesSetup();
    $shift = assignTesShift($s, 10, 12, 5);
    $ids = [
        assignTesRegistrasiDiterima($s)->id,
        assignTesRegistrasiDiterima($s)->id,
        assignTesRegistrasiDiterima($lain)->id,
    ];

    try {
        app(AssignmentService::class)->bulkAssign($s['event'], $ids, $shift, $s['owner']);
        $this->fail('Seharusnya fail-closed 404.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404)
            ->and($shift->refresh()->filled_count)->toBe(0)
            ->and(Assignment::where('event_id', $s['event']->id)->count())->toBe(0);
    }
});

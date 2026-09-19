<?php

use App\Exceptions\ShiftFullException;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{shift: EventShift, event: Event, owner: User, regs: array<int>} */
function opsKuotaPaket(int $kuota = 1, int $pembalap = 3): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($owner->refresh(), $org);

    $event = Event::factory()->create(['organization_id' => $org->id]);
    $division = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'quota' => 50,
        'accepted_count' => 0,
    ]);
    $dasar = now()->addDays(5)->startOfDay();
    $shift = EventShift::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'role_id' => $role->id,
        'start_at' => (clone $dasar)->setTime(9, 0),
        'end_at' => (clone $dasar)->setTime(13, 0),
        'location' => 'Arena Kuota',
        'capacity' => $kuota,
        'filled_count' => 0,
    ]);

    $regs = [];
    foreach (range(1, $pembalap) as $i) {
        $regs[] = (int) Registration::factory()->create([
            'event_id' => $event->id,
            'role_id' => $role->id,
            'status' => 'accepted',
        ])->id;
    }

    return [
        'shift' => $shift->refresh(),
        'event' => $event->refresh(),
        'owner' => $owner->refresh(),
        'regs' => $regs,
    ];
}

it('opsTesKuotaShiftBerebutSlotTerakhir', function (): void {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl tidak tersedia');
    }
    $paket = opsKuotaPaket(1, 3);
    expect((int) $paket['shift']->filled_count)->toBe(0);

    $hasilDir = sys_get_temp_dir().'/shift-kuota-'.getmypid();
    mkdir($hasilDir);
    $shiftId = $paket['shift']->id;
    $ownerId = $paket['owner']->id;
    $pids = [];

    foreach ($paket['regs'] as $regId) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('pcntl_fork gagal membuat proses anak.');
        }
        if ($pid === 0) {
            DB::reconnect();
            $hasilFile = $hasilDir.'/anak-'.getmypid().'.txt';
            try {
                DB::transaction(fn () => app(AssignmentService::class)->assign(
                    Registration::findOrFail($regId),
                    EventShift::findOrFail($shiftId),
                    User::findOrFail($ownerId)
                ));
                file_put_contents($hasilFile, 'assigned');
            } catch (ShiftFullException) {
                file_put_contents($hasilFile, 'full');
            } catch (Throwable $e) {
                file_put_contents($hasilFile, 'error:'.$e->getMessage());
            }
            DB::disconnect();
            exit(0);
        }
        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $hasil = [];
    foreach (glob($hasilDir.'/anak-*.txt') ?: [] as $file) {
        $hasil[] = trim((string) file_get_contents($file));
        unlink($file);
    }
    rmdir($hasilDir);

    $menang = count(array_filter($hasil, fn ($nilai) => $nilai === 'assigned'));
    $penuh = count(array_filter($hasil, fn ($nilai) => $nilai === 'full'));
    $shift = $paket['shift']->refresh();

    expect($menang)->toBe(1)
        ->and($penuh)->toBe(2)
        ->and(Assignment::where('shift_id', $shift->id)->count())->toBe(1)
        ->and((int) $shift->filled_count)->toBe(1)
        ->and((int) $shift->filled_count)->toBeLessThanOrEqual((int) $shift->capacity);
});

it('opsTesKuotaShiftBerurutanSisaSatu', function (): void {
    $paket = opsKuotaPaket(1, 2);
    $svc = app(AssignmentService::class);

    $svc->assign(
        Registration::findOrFail($paket['regs'][0]),
        $paket['shift']->refresh(),
        $paket['owner']
    );
    expect((int) $paket['shift']->refresh()->filled_count)->toBe(1);

    try {
        $svc->assign(
            Registration::findOrFail($paket['regs'][1]),
            $paket['shift']->refresh(),
            $paket['owner']
        );
        $this->fail('Seharusnya melempar ShiftFullException pada assign kedua.');
    } catch (ShiftFullException) {
        $shift = $paket['shift']->refresh();
        expect((int) $shift->filled_count)->toBe(1)
            ->and((int) $shift->filled_count)->toBeLessThanOrEqual((int) $shift->capacity)
            ->and(Assignment::where('shift_id', $shift->id)->count())->toBe(1);
    }
});

it('opsTesKuotaShiftBatalBebaskanSlot', function (): void {
    $paket = opsKuotaPaket(1, 2);
    $svc = app(AssignmentService::class);

    $tugas = $svc->assign(
        Registration::findOrFail($paket['regs'][0]),
        $paket['shift']->refresh(),
        $paket['owner']
    );
    expect((int) $paket['shift']->refresh()->filled_count)->toBe(1);

    $svc->cancel($tugas->refresh(), $paket['owner'], 'Relawan berhalangan hadir.');
    expect((int) $paket['shift']->refresh()->filled_count)->toBe(0);

    $ganti = $svc->assign(
        Registration::findOrFail($paket['regs'][1]),
        $paket['shift']->refresh(),
        $paket['owner']
    );
    $shift = $paket['shift']->refresh();

    expect($ganti->status)->toBe('assigned')
        ->and((int) $shift->filled_count)->toBe(1)
        ->and((int) $shift->filled_count)->toBeLessThanOrEqual((int) $shift->capacity);
});

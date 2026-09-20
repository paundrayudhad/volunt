<?php

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\CertificateService;
use App\Services\EventService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, divisi: EventDivision, role: EventRole} */
function sertParalelPaket(): array
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
    $divisi = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $divisi->id,
        'quota' => 50,
        'accepted_count' => 0,
    ]);

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'divisi' => $divisi->refresh(),
        'role' => $role->refresh(),
    ];
}

function sertParalelSelesaikan(array $s): Event
{
    $svc = app(EventService::class);
    $event = $s['event']->refresh();
    foreach (['published', 'registration_open', 'registration_closed', 'ongoing', 'completed'] as $berikut) {
        $event = $svc->transitionTo($event, $berikut, $s['owner']);
    }

    return $event->refresh();
}

function sertParalelLayak(array $s): User
{
    $relawan = User::factory()->create();
    $reg = Registration::factory()->create([
        'user_id' => $relawan->id,
        'event_id' => $s['event']->id,
        'role_id' => $s['role']->id,
        'status' => 'accepted',
    ]);
    $shift = EventShift::factory()->create([
        'event_id' => $s['event']->id,
        'division_id' => $s['divisi']->id,
        'role_id' => $s['role']->id,
        'start_at' => now()->subDays(4),
        'end_at' => now()->subDays(3),
        'capacity' => 50,
        'filled_count' => 0,
    ]);
    $tugas = app(AssignmentService::class)->assign($reg, $shift, $s['owner']);
    app(AssignmentService::class)->confirm($tugas->refresh(), $s['owner']);
    Attendance::unguarded(fn (): Attendance => Attendance::create([
        'assignment_id' => $tugas->id,
        'shift_id' => $tugas->shift_id,
        'event_id' => $tugas->event_id,
        'user_id' => $tugas->user_id,
        'checked_in_at' => now(),
        'method' => 'manual',
        'status' => 'present',
        'idempotency_key' => (string) Str::uuid(),
    ]));

    return $relawan->refresh();
}

function sertParalelTegasSatuBaris(int $eventId): void
{
    $baris = Certificate::where('event_id', $eventId)->get();

    expect($baris)->toHaveCount(1)
        ->and($baris->pluck('user_id')->unique()->count())->toBe(1)
        ->and($baris->pluck('certificate_no')->unique()->count())->toBe(1)
        ->and(Assignment::where('event_id', $eventId)->where('status', 'completed')->count())->toBe(1);
}

it('sertTesBatchGandaIdempoten', function (): void {
    $s = sertParalelPaket();
    $event = sertParalelSelesaikan($s);
    $relawan = sertParalelLayak($s);
    $eventId = $event->id;
    $ownerId = $s['owner']->id;

    if (! function_exists('pcntl_fork')) {
        $svc = app(CertificateService::class);
        $pertama = $svc->issueBatch($event->refresh(), $s['owner']->refresh());
        $kedua = $svc->issueBatch($event->refresh(), $s['owner']->refresh());

        expect($pertama['issued'])->toHaveCount(1)
            ->and($kedua['issued'])->toHaveCount(0)
            ->and($kedua['skipped'])->toBe(1);
        sertParalelTegasSatuBaris($eventId);
        $this->markTestSkipped('pcntl tidak tersedia: klaim paralel dilewati, idempoten sekuensial terbukti.');
    }

    $hasilDir = sys_get_temp_dir().'/sertifikat-batch-'.getmypid();
    mkdir($hasilDir);
    $pids = [];

    foreach ([0, 1] as $i) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('pcntl_fork gagal membuat proses anak.');
        }
        if ($pid === 0) {
            DB::reconnect();
            $hasilFile = $hasilDir.'/anak-'.getmypid().'-'.$i.'.txt';
            try {
                $hasil = DB::transaction(fn () => app(CertificateService::class)->issueBatch(
                    Event::findOrFail($eventId),
                    User::findOrFail($ownerId)
                ));
                file_put_contents($hasilFile, 'issued:'.count($hasil['issued']).',skipped:'.$hasil['skipped']);
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

    $salah = array_filter($hasil, fn ($nilai) => str_starts_with($nilai, 'error:'));
    expect($salah)->toBeEmpty();
    sertParalelTegasSatuBaris($eventId);
    expect($relawan->refresh()->id)->not->toBeNull();
});

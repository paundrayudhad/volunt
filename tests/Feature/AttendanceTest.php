<?php

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\AttendanceService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, role: EventRole, shift: EventShift, registration: Registration, assignment: Assignment} */
function hadirTesSetup(): array
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
    $mulai = now()->addHours(2);
    $shift = EventShift::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'role_id' => $role->id,
        'start_at' => (clone $mulai),
        'end_at' => (clone $mulai)->addHours(4),
        'capacity' => null,
        'filled_count' => 0,
    ]);

    $registration = Registration::factory()->create([
        'event_id' => $event->id,
        'role_id' => $role->id,
        'status' => 'accepted',
    ]);
    $assignment = app(AssignmentService::class)->assign($registration, $shift, $owner->refresh());

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'role' => $role->refresh(),
        'shift' => $shift->refresh(),
        'registration' => $registration->refresh(),
        'assignment' => $assignment->refresh(),
    ];
}

/** @return array<string, mixed> */
function hadirTesPindai(string $mentah, string $aksi = 'check_in'): array
{
    return [
        'token' => $mentah,
        'action' => $aksi,
        'idempotency_key' => (string) Str::uuid(),
    ];
}

it('hadirTesCheckInValidPresentDanLog', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(5));
    $mentah = app(AttendanceService::class)->issueToken($setup['assignment'], $setup['owner'])['raw'];

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($mentah))
        ->assertRedirect(route('organizer.events.attendances.index', [$setup['org']->slug, $setup['event']->slug]));

    $hadir = Attendance::where('assignment_id', $setup['assignment']->id)->firstOrFail();
    expect($hadir->status)->toBe('present')
        ->and($hadir->checked_in_at)->not->toBeNull()
        ->and($hadir->method)->toBe('qr')
        ->and(AttendanceLog::where('attendance_id', $hadir->id)->where('action', 'check_in')->count())->toBe(1)
        ->and(AttendanceLog::where('attendance_id', $hadir->id)->whereNotNull('ip')->count())->toBe(1)
        ->and(AuditLog::where('action', 'attendance.check_in')->where('resource_id', (string) $hadir->id)->exists())->toBeTrue();
});

it('hadirTesCheckInTelatLate', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(20));
    $mentah = app(AttendanceService::class)->issueToken($setup['assignment'], $setup['owner'])['raw'];

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($mentah))
        ->assertRedirect();

    expect(Attendance::where('assignment_id', $setup['assignment']->id)->firstOrFail()->status)->toBe('late');
});

it('hadirTesLuarJendela422', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->subMinutes(40));
    $mentah = app(AttendanceService::class)->issueToken($setup['assignment'], $setup['owner'])['raw'];

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($mentah))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Di luar jendela kehadiran.');

    $this->travelTo((clone $setup['shift']->end_at)->addMinutes(5));

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($mentah))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Di luar jendela kehadiran.');

    expect(Attendance::where('assignment_id', $setup['assignment']->id)->count())->toBe(0);
});

it('hadirTesTokenExpired422', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(5));
    $mentah = app(AttendanceService::class)->issueToken($setup['assignment'], $setup['owner'])['raw'];
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(11));

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($mentah))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Token kedaluwarsa.');

    expect(Attendance::where('assignment_id', $setup['assignment']->id)->count())->toBe(0);
});

it('hadirTesTokenBekasPakai422', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(5));
    $mentah = app(AttendanceService::class)->issueToken($setup['assignment'], $setup['owner'])['raw'];

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($mentah))
        ->assertRedirect();

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($mentah))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Token sudah dipakai.');

    expect(Attendance::where('assignment_id', $setup['assignment']->id)->count())->toBe(1);
});

it('hadirTesReplayIdempotencyExisting', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(5));
    $mentah = app(AttendanceService::class)->issueToken($setup['assignment'], $setup['owner'])['raw'];
    $kunci = (string) Str::uuid();
    $payload = ['token' => $mentah, 'action' => 'check_in', 'idempotency_key' => $kunci];

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), $payload)
        ->assertRedirect();

    $pertama = Attendance::where('assignment_id', $setup['assignment']->id)->firstOrFail();

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), $payload)
        ->assertRedirect();

    expect(Attendance::where('assignment_id', $setup['assignment']->id)->count())->toBe(1)
        ->and(Attendance::where('idempotency_key', $kunci)->firstOrFail()->id)->toBe($pertama->id);
});

it('hadirTesTokenAssignmentLain404', function (): void {
    $setup = hadirTesSetup();
    $lain = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(5));
    $mentahAsing = app(AttendanceService::class)->issueToken($lain['assignment'], $lain['owner'])['raw'];

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($mentahAsing))
        ->assertNotFound()
        ->assertJsonPath('message', 'Token tidak termasuk event ini.');

    expect(Attendance::where('assignment_id', $lain['assignment']->id)->count())->toBe(0);
});

it('hadirTesCheckOutTanpaCheckIn422', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(30));
    $mentah = app(AttendanceService::class)->issueToken($setup['assignment'], $setup['owner'])['raw'];

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($mentah, 'check_out'))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Belum check-in.');

    expect(Attendance::where('assignment_id', $setup['assignment']->id)->count())->toBe(0);
});

it('hadirTesCheckOutValidTerisi', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(30));
    $layanan = app(AttendanceService::class);
    $masuk = $layanan->issueToken($setup['assignment'], $setup['owner'])['raw'];

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($masuk))
        ->assertRedirect();

    $keluar = $layanan->issueToken($setup['assignment'], $setup['owner'])['raw'];

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($keluar, 'check_out'))
        ->assertRedirect();

    $hadir = Attendance::where('assignment_id', $setup['assignment']->id)->firstOrFail();
    expect($hadir->checked_in_at)->not->toBeNull()
        ->and($hadir->checked_out_at)->not->toBeNull()
        ->and(AttendanceLog::where('attendance_id', $hadir->id)->where('action', 'check_out')->count())->toBe(1);
});

it('hadirTesCheckOutReplayIdempotencySama', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(30));
    $layanan = app(AttendanceService::class);
    $masuk = $layanan->issueToken($setup['assignment'], $setup['owner'])['raw'];

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($masuk))
        ->assertRedirect();

    $keluar = $layanan->issueToken($setup['assignment'], $setup['owner'])['raw'];
    $kunci = (string) Str::uuid();
    $payload = ['token' => $keluar, 'action' => 'check_out', 'idempotency_key' => $kunci];

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), $payload)
        ->assertRedirect();

    $pertama = Attendance::where('assignment_id', $setup['assignment']->id)->firstOrFail();
    $keluarPada = (string) $pertama->checked_out_at;
    $jumlahLog = AttendanceLog::where('attendance_id', $pertama->id)->where('action', 'check_out')->count();

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), $payload)
        ->assertRedirect();

    $kedua = Attendance::where('assignment_id', $setup['assignment']->id)->firstOrFail();
    expect($kedua->id)->toBe($pertama->id)
        ->and((string) $kedua->checked_out_at)->toBe($keluarPada)
        ->and(Attendance::where('assignment_id', $setup['assignment']->id)->count())->toBe(1)
        ->and(AttendanceLog::where('attendance_id', $pertama->id)->where('action', 'check_out')->count())->toBe($jumlahLog);
});

it('hadirTesManualTanpaAlasan422', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(5));

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.attendances.manual', [$setup['org']->slug, $setup['event']->slug]), [
            'assignment_id' => $setup['assignment']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect(Attendance::where('assignment_id', $setup['assignment']->id)->count())->toBe(0);
});

it('hadirTesManualValidMethodManual', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(5));

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.attendances.manual', [$setup['org']->slug, $setup['event']->slug]), [
            'assignment_id' => $setup['assignment']->id,
            'reason' => 'Pemindai rusak, dicatat manual oleh koordinator.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('organizer.events.attendances.index', [$setup['org']->slug, $setup['event']->slug]));

    $hadir = Attendance::where('assignment_id', $setup['assignment']->id)->firstOrFail();
    expect($hadir->method)->toBe('manual')
        ->and($hadir->status)->toBe('present')
        ->and($hadir->checked_in_at)->not->toBeNull();
});

it('hadirTesRotateMencabutLama', function (): void {
    $setup = hadirTesSetup();
    $this->travelTo((clone $setup['shift']->start_at)->addMinutes(5));
    $relawan = $setup['registration']->user()->firstOrFail();
    $lama = app(AttendanceService::class)->issueToken($setup['assignment'], $setup['owner'])['raw'];

    $this->actingAs($relawan)
        ->get(route('my.qr.show', $setup['assignment']->id))
        ->assertOk();

    $this->actingAs($relawan)
        ->post(route('my.qr.rotate', $setup['assignment']->id))
        ->assertRedirect()
        ->assertSessionHas('qr_token');

    $baru = session('qr_token');
    expect($baru)->toBeString()
        ->and($baru)->not->toBe($lama);

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.attendances.process', [$setup['org']->slug, $setup['event']->slug]), hadirTesPindai($lama))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Token sudah dicabut.');
});

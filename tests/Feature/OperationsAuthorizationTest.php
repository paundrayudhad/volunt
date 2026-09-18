<?php

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\AssignmentService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, role: EventRole, shift: EventShift} */
function opsJadwalPaket(?int $kapasitas = null): array
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
        'location' => 'Gedung Serbaguna A',
        'capacity' => $kapasitas,
        'filled_count' => 0,
    ]);

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'role' => $role->refresh(),
        'shift' => $shift->refresh(),
    ];
}

/** @param  array<string>  $izin */
function opsJadwalStaf(array $paket, string $email, array $izin = []): User
{
    $staf = User::factory()->create(['email' => $email]);
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $paket['org']->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staf->refresh(), $paket['org']);
    if ($izin !== []) {
        $staf->refresh()->givePermissionTo(...$izin);
    }

    return $staf->refresh();
}

/** @return array{user: User, assignment: Assignment} */
function opsJadwalTugas(array $paket, ?User $relawan = null, ?EventShift $shift = null): array
{
    $relawan ??= User::factory()->create();
    $reg = Registration::factory()->create([
        'user_id' => $relawan->id,
        'event_id' => $paket['event']->id,
        'role_id' => $paket['role']->id,
        'status' => 'accepted',
    ]);
    $tugas = app(AssignmentService::class)->assign(
        $reg->refresh(),
        ($shift ?? $paket['shift'])->refresh(),
        $paket['owner']->refresh()
    );

    return ['user' => $relawan->refresh(), 'assignment' => $tugas->refresh()];
}

function opsJadwalDraf(array $paket, array $override = []): Announcement
{
    return Announcement::unguarded(fn () => Announcement::create(array_merge([
        'event_id' => $paket['event']->id,
        'author_id' => $paket['owner']->id,
        'target_type' => 'event',
        'target_id' => null,
        'title' => 'Pengumuman operasi '.Str::random(8),
        'body' => 'Isi pengumuman operasi untuk relawan event ini.',
        'published_at' => null,
        'expires_at' => null,
    ], $override)));
}

/** @return array<string, int> */
function opsJadwalKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('opsTesGuestDiarahkanKeLogin', function (): void {
    $paket = opsJadwalPaket();
    ['assignment' => $tugas] = opsJadwalTugas($paket);
    $draf = opsJadwalDraf($paket);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->get(route('my.schedule'))->assertRedirect(route('login'));
    $this->get(route('my.qr.show', $tugas->id))->assertRedirect(route('login'));
    $this->post(route('my.qr.rotate', $tugas->id))->assertRedirect(route('login'));
    $this->get(route('announcements.index'))->assertRedirect(route('login'));
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->post(route('notifications.read', (string) Str::uuid()))->assertRedirect(route('login'));

    $this->get(route('organizer.events.assignments.index', [$org, $event]))->assertRedirect(route('login'));
    $this->get(route('organizer.events.assignments.show', [$org, $event, $tugas->id]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.assignments.assign', [$org, $event]), [
        'registration_id' => $tugas->registration_id,
        'shift_id' => $paket['shift']->id,
    ])->assertRedirect(route('login'));
    $this->post(route('organizer.events.assignments.bulk', [$org, $event]), [
        'ids' => [$tugas->registration_id],
        'shift_id' => $paket['shift']->id,
    ])->assertRedirect(route('login'));
    $this->post(route('organizer.events.assignments.reassign', [$org, $event, $tugas->id]), [
        'shift_id' => $paket['shift']->id,
    ])->assertRedirect(route('login'));
    $this->post(route('organizer.events.assignments.confirm', [$org, $event, $tugas->id]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.assignments.cancel', [$org, $event, $tugas->id]), [
        'reason' => 'Relawan berhalangan.',
    ])->assertRedirect(route('login'));

    $this->get(route('organizer.events.attendances.index', [$org, $event]))->assertRedirect(route('login'));
    $this->get(route('organizer.events.attendances.scan', [$org, $event]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.attendances.process', [$org, $event]), [
        'token' => str_repeat('a', 64),
        'action' => 'check_in',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect(route('login'));
    $this->post(route('organizer.events.attendances.manual', [$org, $event]), [
        'assignment_id' => $tugas->id,
        'reason' => 'Pemindai rusak saat acara berlangsung.',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect(route('login'));

    $this->get(route('organizer.events.announcements.index', [$org, $event]))->assertRedirect(route('login'));
    $this->get(route('organizer.events.announcements.create', [$org, $event]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.announcements.store', [$org, $event]), [
        'title' => 'Judul',
        'body' => 'Isi pengumuman.',
        'target_type' => 'event',
    ])->assertRedirect(route('login'));
    $this->get(route('organizer.events.announcements.show', [$org, $event, $draf->id]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.announcements.publish', [$org, $event, $draf->id]))->assertRedirect(route('login'));
});

it('opsTesStafReadOnly403SemuaMutasi', function (): void {
    $paket = opsJadwalPaket(5);
    $staf = opsJadwalStaf($paket, 'staf-read-only@ops.test', ['assignment.read', 'attendance.read', 'announcement.read']);
    ['assignment' => $tugas] = opsJadwalTugas($paket);
    $draf = opsJadwalDraf($paket);
    $reg = Registration::factory()->create([
        'event_id' => $paket['event']->id,
        'role_id' => $paket['role']->id,
        'status' => 'accepted',
    ]);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($staf)
        ->get(route('organizer.events.assignments.index', [$org, $event]))
        ->assertOk();
    $this->actingAs($staf)->withSession(opsJadwalKonfirmasi())
        ->post(route('organizer.events.assignments.assign', [$org, $event]), [
            'registration_id' => $reg->id,
            'shift_id' => $paket['shift']->id,
        ])
        ->assertForbidden();
    $this->actingAs($staf)->withSession(opsJadwalKonfirmasi())
        ->post(route('organizer.events.assignments.bulk', [$org, $event]), [
            'ids' => [$reg->id],
            'shift_id' => $paket['shift']->id,
        ])
        ->assertForbidden();
    $this->actingAs($staf)
        ->post(route('organizer.events.assignments.reassign', [$org, $event, $tugas->id]), [
            'shift_id' => $paket['shift']->id,
        ])
        ->assertForbidden();
    $this->actingAs($staf)
        ->post(route('organizer.events.assignments.confirm', [$org, $event, $tugas->id]))
        ->assertForbidden();
    $this->actingAs($staf)
        ->post(route('organizer.events.assignments.cancel', [$org, $event, $tugas->id]), [
            'reason' => 'Relawan berhalangan.',
        ])
        ->assertForbidden();

    $this->actingAs($staf)
        ->get(route('organizer.events.attendances.index', [$org, $event]))
        ->assertOk();
    $this->actingAs($staf)
        ->get(route('organizer.events.attendances.scan', [$org, $event]))
        ->assertForbidden();
    $this->actingAs($staf)
        ->post(route('organizer.events.attendances.process', [$org, $event]), [
            'token' => str_repeat('b', 64),
            'action' => 'check_in',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertForbidden();
    $this->actingAs($staf)
        ->post(route('organizer.events.attendances.manual', [$org, $event]), [
            'assignment_id' => $tugas->id,
            'reason' => 'Pemindai rusak saat acara berlangsung.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertForbidden();

    $this->actingAs($staf)
        ->get(route('organizer.events.announcements.index', [$org, $event]))
        ->assertOk();
    $this->actingAs($staf)
        ->get(route('organizer.events.announcements.create', [$org, $event]))
        ->assertForbidden();
    $this->actingAs($staf)
        ->post(route('organizer.events.announcements.store', [$org, $event]), [
            'title' => 'Judul staf',
            'body' => 'Isi pengumuman staf.',
            'target_type' => 'event',
        ])
        ->assertForbidden();
    $this->actingAs($staf)
        ->post(route('organizer.events.announcements.publish', [$org, $event, $draf->id]))
        ->assertForbidden();

    expect(Assignment::where('registration_id', $reg->id)->exists())->toBeFalse()
        ->and($draf->refresh()->published_at)->toBeNull();
});

it('opsTesLintasOrgEvent404', function (): void {
    $paket = opsJadwalPaket();
    $lain = opsJadwalPaket();
    $stafLain = opsJadwalStaf($lain, 'staf-org-lain@ops.test', ['assignment.read', 'attendance.read', 'announcement.read']);
    ['assignment' => $tugas] = opsJadwalTugas($paket);
    $draf = opsJadwalDraf($paket);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($stafLain)
        ->get(route('organizer.events.assignments.index', [$org, $event]))
        ->assertNotFound();
    $this->actingAs($stafLain)
        ->get(route('organizer.events.assignments.show', [$org, $event, $tugas->id]))
        ->assertNotFound();
    $this->actingAs($stafLain)
        ->get(route('organizer.events.attendances.index', [$org, $event]))
        ->assertNotFound();
    $this->actingAs($stafLain)
        ->get(route('organizer.events.announcements.show', [$org, $event, $draf->id]))
        ->assertNotFound();

    $eventKedua = Event::factory()->create(['organization_id' => $paket['org']->id]);
    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.assignments.show', [$org, $eventKedua->slug, $tugas->id]))
        ->assertNotFound();
    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.announcements.show', [$org, $eventKedua->slug, $draf->id]))
        ->assertNotFound();
});

it('opsTesJadwalMilikSendiri', function (): void {
    $paket = opsJadwalPaket();
    $dasar = now()->addDays(6)->startOfDay();
    $shiftLain = EventShift::factory()->create([
        'event_id' => $paket['event']->id,
        'start_at' => (clone $dasar)->setTime(9, 0),
        'end_at' => (clone $dasar)->setTime(13, 0),
        'location' => 'Gudang Logistik B',
        'capacity' => null,
        'filled_count' => 0,
    ]);
    ['user' => $aku, 'assignment' => $tugasku] = opsJadwalTugas($paket);
    ['assignment' => $tugasDia] = opsJadwalTugas($paket, null, $shiftLain->refresh());

    $this->actingAs($aku)->get(route('my.schedule'))
        ->assertOk()
        ->assertSee($paket['event']->name)
        ->assertSee('Gedung Serbaguna A')
        ->assertSee('assigned')
        ->assertDontSee('Gudang Logistik B');

    expect($tugasDia->refresh()->status)->toBe('assigned');

    $this->actingAs($aku)->get(route('my.qr.show', $tugasku->id))->assertOk();
    $this->actingAs($aku)->get(route('my.qr.show', $tugasDia->id))->assertNotFound();
});

it('opsTesJadwalMenampilkanStatusKehadiran', function (): void {
    $paket = opsJadwalPaket();
    ['user' => $aku, 'assignment' => $tugasku] = opsJadwalTugas($paket);
    Attendance::unguarded(fn () => Attendance::create([
        'assignment_id' => $tugasku->id,
        'shift_id' => $paket['shift']->id,
        'event_id' => $paket['event']->id,
        'user_id' => $aku->id,
        'checked_in_at' => now(),
        'method' => 'qr',
        'status' => 'present',
        'idempotency_key' => (string) Str::uuid(),
    ]));

    $this->actingAs($aku)->get(route('my.schedule'))
        ->assertOk()
        ->assertSee('present');

    ['user' => $baru] = opsJadwalTugas($paket);
    $this->actingAs($baru)->get(route('my.schedule'))
        ->assertOk()
        ->assertSee('Belum dicatat');
});

it('opsTesOwnerMengelolaOperasi', function (): void {
    $paket = opsJadwalPaket(5);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;
    $jendela = EventShift::factory()->create([
        'event_id' => $paket['event']->id,
        'start_at' => now()->subHour(),
        'end_at' => now()->addHours(3),
        'location' => 'Arena Kehadiran',
        'capacity' => 5,
        'filled_count' => 0,
    ]);
    $reg = Registration::factory()->create([
        'event_id' => $paket['event']->id,
        'role_id' => $paket['role']->id,
        'status' => 'accepted',
    ]);

    $this->actingAs($paket['owner'])->withSession(opsJadwalKonfirmasi())
        ->post(route('organizer.events.assignments.assign', [$org, $event]), [
            'registration_id' => $reg->id,
            'shift_id' => $jendela->id,
        ])
        ->assertRedirect();
    $tugas = Assignment::where('registration_id', $reg->id)->firstOrFail();

    $this->actingAs($paket['owner'])
        ->post(route('organizer.events.attendances.manual', [$org, $event]), [
            'assignment_id' => $tugas->id,
            'reason' => 'Pemindai rusak saat acara berlangsung.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect();

    $draf = opsJadwalDraf($paket);
    $this->actingAs($paket['owner'])
        ->post(route('organizer.events.announcements.publish', [$org, $event, $draf->id]))
        ->assertRedirect();

    expect($tugas->refresh()->status)->toBe('assigned')
        ->and(Attendance::where('assignment_id', $tugas->id)->where('method', 'manual')->exists())->toBeTrue()
        ->and($draf->refresh()->published_at)->not->toBeNull();
});

it('opsTesNotifikasiHanyaMilikSendiri', function (): void {
    $paket = opsJadwalPaket();
    ['user' => $aku] = opsJadwalTugas($paket);
    ['user' => $dia] = opsJadwalTugas($paket);
    $draf = opsJadwalDraf($paket);
    app(AnnouncementService::class)->publish($draf->refresh(), $paket['owner']);
    $notifku = $aku->refresh()->notifications()->firstOrFail();

    $this->actingAs($dia)->post(route('notifications.read', $notifku->id))->assertNotFound();
    $this->actingAs($aku)->post(route('notifications.read', $notifku->id))->assertRedirect(route('notifications.index'));

    expect($notifku->refresh()->read_at)->not->toBeNull();
});

it('opsTesAdminTanpaRuteOperations', function (): void {
    expect(Route::has('admin.assignments.index'))->toBeFalse()
        ->and(Route::has('admin.attendances.index'))->toBeFalse()
        ->and(Route::has('admin.announcements.index'))->toBeFalse()
        ->and(Route::has('admin.registrations.index'))->toBeTrue();
});

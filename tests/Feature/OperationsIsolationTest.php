<?php

use App\Models\Announcement;
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
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, shift: EventShift} */
function opsIsolasiPaket(string $nama, string $lokasi): array
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

    $event = Event::factory()->create(['organization_id' => $org->id, 'name' => $nama]);
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
        'location' => $lokasi,
        'capacity' => null,
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

/** @return array{paketA: array<string, mixed>, paketB: array<string, mixed>, tugasA: Assignment, tugasB: Assignment, userA: User, userB: User} */
function opsIsolasiPasangan(): array
{
    $paketA = opsIsolasiPaket('Festival Jadwal A', 'Panggung Rahasia A');
    $paketB = opsIsolasiPaket('Festival Jadwal B', 'Panggung Rahasia B');
    ['user' => $userA, 'assignment' => $tugasA] = opsIsolasiTugas($paketA);
    ['user' => $userB, 'assignment' => $tugasB] = opsIsolasiTugas($paketB);

    return [
        'paketA' => $paketA,
        'paketB' => $paketB,
        'tugasA' => $tugasA,
        'tugasB' => $tugasB,
        'userA' => $userA,
        'userB' => $userB,
    ];
}

/** @return array{user: User, assignment: Assignment} */
function opsIsolasiTugas(array $paket, ?User $relawan = null): array
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
        $paket['shift']->refresh(),
        $paket['owner']->refresh()
    );

    return ['user' => $relawan->refresh(), 'assignment' => $tugas->refresh()];
}

function opsIsolasiDraf(array $paket): Announcement
{
    return Announcement::unguarded(fn () => Announcement::create([
        'event_id' => $paket['event']->id,
        'author_id' => $paket['owner']->id,
        'target_type' => 'event',
        'target_id' => null,
        'title' => 'Pengumuman isolasi '.$paket['event']->name,
        'body' => 'Isi pengumuman isolasi antar organisasi.',
        'published_at' => null,
        'expires_at' => null,
    ]));
}

/** @return array<string, int> */
function opsIsolasiKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('opsTesIsolasiJadwalSimetris', function (): void {
    $pair = opsIsolasiPasangan();
    [$paketA, $paketB, $userA, $userB] = [$pair['paketA'], $pair['paketB'], $pair['userA'], $pair['userB']];

    $this->actingAs($userA)->get(route('my.schedule'))
        ->assertOk()
        ->assertSee('Festival Jadwal A')
        ->assertSee('Panggung Rahasia A')
        ->assertDontSee('Festival Jadwal B')
        ->assertDontSee('Panggung Rahasia B');

    $this->actingAs($userB)->get(route('my.schedule'))
        ->assertOk()
        ->assertSee('Festival Jadwal B')
        ->assertSee('Panggung Rahasia B')
        ->assertDontSee('Festival Jadwal A')
        ->assertDontSee('Panggung Rahasia A');

    expect($paketA['event']->slug)->not->toBe($paketB['event']->slug);
});

it('opsTesIsolasiBacaSilang404', function (): void {
    $pair = opsIsolasiPasangan();
    [$paketA, $paketB, $tugasA, $tugasB] = [$pair['paketA'], $pair['paketB'], $pair['tugasA'], $pair['tugasB']];
    $drafA = opsIsolasiDraf($paketA);
    $drafB = opsIsolasiDraf($paketB);

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.assignments.show', [$paketA['org']->slug, $paketA['event']->slug, $tugasB->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.assignments.show', [$paketB['org']->slug, $paketB['event']->slug, $tugasA->id]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.attendances.index', [$paketB['org']->slug, $paketB['event']->slug]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.attendances.index', [$paketA['org']->slug, $paketA['event']->slug]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.announcements.show', [$paketA['org']->slug, $paketA['event']->slug, $drafB->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.announcements.show', [$paketB['org']->slug, $paketB['event']->slug, $drafA->id]))
        ->assertNotFound();
});

it('opsTesIsolasiMutasiSilang404DataUtuh', function (): void {
    $pair = opsIsolasiPasangan();
    [$paketA, $paketB, $tugasA, $tugasB] = [$pair['paketA'], $pair['paketB'], $pair['tugasA'], $pair['tugasB']];
    $drafA = opsIsolasiDraf($paketA);
    $drafB = opsIsolasiDraf($paketB);
    $regA = Registration::factory()->create([
        'event_id' => $paketA['event']->id,
        'role_id' => $paketA['role']->id,
        'status' => 'accepted',
    ]);

    $this->actingAs($paketA['owner'])->withSession(opsIsolasiKonfirmasi())
        ->post(route('organizer.events.assignments.assign', [$paketA['org']->slug, $paketA['event']->slug]), [
            'registration_id' => $regA->id,
            'shift_id' => $paketB['shift']->id,
        ])
        ->assertNotFound();
    $this->actingAs($paketB['owner'])->withSession(opsIsolasiKonfirmasi())
        ->postJson(route('organizer.events.assignments.bulk', [$paketB['org']->slug, $paketB['event']->slug]), [
            'ids' => [$tugasA->registration_id],
            'shift_id' => $paketB['shift']->id,
        ])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.attendances.manual', [$paketA['org']->slug, $paketA['event']->slug]), [
            'assignment_id' => $tugasB->id,
            'reason' => 'Alasan silang antar organisasi.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->post(route('organizer.events.attendances.manual', [$paketB['org']->slug, $paketB['event']->slug]), [
            'assignment_id' => $tugasA->id,
            'reason' => 'Alasan silang antar organisasi.',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.announcements.publish', [$paketA['org']->slug, $paketA['event']->slug, $drafB->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->post(route('organizer.events.announcements.publish', [$paketB['org']->slug, $paketB['event']->slug, $drafA->id]))
        ->assertNotFound();

    expect($tugasA->refresh()->status)->toBe('assigned')
        ->and($tugasB->refresh()->status)->toBe('assigned')
        ->and($drafA->refresh()->published_at)->toBeNull()
        ->and($drafB->refresh()->published_at)->toBeNull()
        ->and(Assignment::where('registration_id', $regA->id)->exists())->toBeFalse();
});

it('opsTesIsolasiKatalogTakBocor', function (): void {
    $pair = opsIsolasiPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];
    $paketA['event']->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $paketB['event']->forceFill(['status' => 'published', 'published_at' => now()])->save();

    $this->get(route('events.index'))
        ->assertOk()
        ->assertSee('Festival Jadwal A')
        ->assertSee('Festival Jadwal B')
        ->assertDontSee('Panggung Rahasia A')
        ->assertDontSee('Panggung Rahasia B');

    $this->get(route('events.show', $paketA['event']->slug))
        ->assertOk()
        ->assertDontSee($pair['userA']->name)
        ->assertDontSee($pair['userB']->name)
        ->assertDontSee('Isi pengumuman isolasi antar organisasi');
});

it('opsTesIsolasiRelawanLuar404JadwalKosong', function (): void {
    $pair = opsIsolasiPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];
    $luar = User::factory()->create();

    $this->actingAs($luar)
        ->get(route('organizer.events.assignments.index', [$paketA['org']->slug, $paketA['event']->slug]))
        ->assertNotFound();
    $this->actingAs($luar)
        ->get(route('organizer.events.assignments.index', [$paketB['org']->slug, $paketB['event']->slug]))
        ->assertNotFound();
    $this->actingAs($luar)->get(route('my.schedule'))
        ->assertOk()
        ->assertSee('Belum ada jadwal');
});

<?php

use App\Jobs\BroadcastAnnouncement;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AuditLog;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, division: EventDivision, role: EventRole, roleLain: EventRole, shift: EventShift, shiftLain: EventShift} */
function umumTesSetup(): array
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
    $roleLain = EventRole::factory()->create([
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
        'start_at' => (clone $dasar)->setTime(10, 0),
        'end_at' => (clone $dasar)->setTime(14, 0),
        'capacity' => null,
        'filled_count' => 0,
    ]);
    $shiftLain = EventShift::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'role_id' => $roleLain->id,
        'start_at' => (clone $dasar)->setTime(16, 0),
        'end_at' => (clone $dasar)->setTime(20, 0),
        'capacity' => null,
        'filled_count' => 0,
    ]);

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'division' => $division->refresh(),
        'role' => $role->refresh(),
        'roleLain' => $roleLain->refresh(),
        'shift' => $shift->refresh(),
        'shiftLain' => $shiftLain->refresh(),
    ];
}

/** @return array{user: User, assignment: Assignment} */
function umumTesTugaskan(array $setup, ?User $user = null, ?EventRole $role = null, ?EventShift $shift = null): array
{
    $user ??= User::factory()->create();
    $role ??= $setup['role'];
    $shift ??= $setup['shift'];
    $reg = Registration::factory()->create([
        'user_id' => $user->id,
        'event_id' => $setup['event']->id,
        'role_id' => $role->id,
        'status' => 'accepted',
    ]);
    $assignment = app(AssignmentService::class)->assign($reg->refresh(), $shift->refresh(), $setup['owner']->refresh());

    return ['user' => $user->refresh(), 'assignment' => $assignment->refresh()];
}

function umumTesDraf(array $setup, array $override = []): Announcement
{
    return Announcement::unguarded(fn () => Announcement::create(array_merge([
        'event_id' => $setup['event']->id,
        'author_id' => $setup['owner']->id,
        'target_type' => 'event',
        'target_id' => null,
        'title' => 'Pengumuman '.Str::random(8),
        'body' => 'Isi pengumuman untuk relawan event ini.',
        'published_at' => null,
        'expires_at' => null,
    ], $override)));
}

/** @return array<int, mixed> */
function umumTesParam(array $setup, ?Announcement $pengumuman = null): array
{
    $param = [$setup['org']->slug, $setup['event']->slug];
    if ($pengumuman instanceof Announcement) {
        $param[] = $pengumuman->id;
    }

    return $param;
}

it('umumTesDraftTakTerlihatVolunteer', function (): void {
    $setup = umumTesSetup();
    ['user' => $relawan] = umumTesTugaskan($setup);
    $draf = umumTesDraf($setup);

    $this->actingAs($relawan)
        ->get(route('announcements.index'))
        ->assertOk()
        ->assertDontSee($draf->title);

    expect($relawan->refresh()->notifications()->count())->toBe(0);
});

it('umumTesTerbitEventSemuaAcceptedDapatNotifikasi', function (): void {
    $setup = umumTesSetup();
    ['user' => $satu] = umumTesTugaskan($setup);
    ['user' => $dua] = umumTesTugaskan($setup);
    $draf = umumTesDraf($setup);

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.announcements.publish', umumTesParam($setup, $draf)))
        ->assertRedirect(route('organizer.events.announcements.show', umumTesParam($setup, $draf)));

    expect($draf->refresh()->published_at)->not->toBeNull()
        ->and($satu->refresh()->unreadNotifications()->count())->toBe(1)
        ->and($dua->refresh()->unreadNotifications()->count())->toBe(1)
        ->and(AuditLog::where('action', 'announcement.publish')->where('resource_id', (string) $draf->id)->exists())->toBeTrue();
});

it('umumTesTerbitDispatchKeAntrean', function (): void {
    Queue::fake();
    $setup = umumTesSetup();
    ['user' => $relawan] = umumTesTugaskan($setup);
    $draf = umumTesDraf($setup);

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.announcements.publish', umumTesParam($setup, $draf)))
        ->assertRedirect();

    Queue::assertPushed(BroadcastAnnouncement::class, fn ($job): bool => $job->announcementId === $draf->id);
    expect($relawan->refresh()->unreadNotifications()->count())->toBe(0);
});

it('umumTesTargetRoleHanyaRoleItu', function (): void {
    $setup = umumTesSetup();
    ['user' => $tepat] = umumTesTugaskan($setup, null, $setup['role'], $setup['shift']);
    ['user' => $lain] = umumTesTugaskan($setup, null, $setup['roleLain'], $setup['shiftLain']);
    $draf = umumTesDraf($setup, ['target_type' => 'role', 'target_id' => $setup['role']->id]);

    app(AnnouncementService::class)->publish($draf->refresh(), $setup['owner']);

    expect($tepat->refresh()->unreadNotifications()->count())->toBe(1)
        ->and($lain->refresh()->unreadNotifications()->count())->toBe(0);
});

it('umumTesTargetShiftHanyaShiftItu', function (): void {
    $setup = umumTesSetup();
    ['user' => $tepat] = umumTesTugaskan($setup, null, $setup['role'], $setup['shift']);
    ['user' => $lain] = umumTesTugaskan($setup, null, $setup['roleLain'], $setup['shiftLain']);
    $draf = umumTesDraf($setup, ['target_type' => 'shift', 'target_id' => $setup['shift']->id]);

    app(AnnouncementService::class)->publish($draf->refresh(), $setup['owner']);

    expect($tepat->refresh()->unreadNotifications()->count())->toBe(1)
        ->and($lain->refresh()->unreadNotifications()->count())->toBe(0);
});

it('umumTesTargetIndividuSatuUser', function (): void {
    $setup = umumTesSetup();
    ['user' => $tepat] = umumTesTugaskan($setup);
    ['user' => $lain] = umumTesTugaskan($setup);
    $draf = umumTesDraf($setup, ['target_type' => 'individual', 'target_id' => $tepat->id]);

    app(AnnouncementService::class)->publish($draf->refresh(), $setup['owner']);

    expect($tepat->refresh()->unreadNotifications()->count())->toBe(1)
        ->and($lain->refresh()->unreadNotifications()->count())->toBe(0);
});

it('umumTesNonTargetTakMelihatPengumuman', function (): void {
    $setup = umumTesSetup();
    ['user' => $tepat] = umumTesTugaskan($setup, null, $setup['role'], $setup['shift']);
    ['user' => $asing] = umumTesTugaskan($setup, null, $setup['roleLain'], $setup['shiftLain']);
    $draf = umumTesDraf($setup, ['target_type' => 'role', 'target_id' => $setup['role']->id]);
    app(AnnouncementService::class)->publish($draf->refresh(), $setup['owner']);

    $this->actingAs($tepat)
        ->get(route('announcements.index'))
        ->assertOk()
        ->assertSee($draf->title);

    $this->actingAs($asing)
        ->get(route('announcements.index'))
        ->assertOk()
        ->assertDontSee($draf->title);
});

it('umumTesExpiredTakTampil', function (): void {
    $setup = umumTesSetup();
    ['user' => $relawan] = umumTesTugaskan($setup);
    $draf = umumTesDraf($setup, ['expires_at' => now()->subHour()]);
    app(AnnouncementService::class)->publish($draf->refresh(), $setup['owner']);

    $this->actingAs($relawan)
        ->get(route('announcements.index'))
        ->assertOk()
        ->assertDontSee($draf->title);
});

it('umumTesStaffTanpaIzinDitolak', function (): void {
    $setup = umumTesSetup();
    $staf = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $setup['org']->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staf->refresh(), $setup['org']);
    $draf = umumTesDraf($setup);

    $this->actingAs($staf)
        ->get(route('organizer.events.announcements.index', umumTesParam($setup)))
        ->assertForbidden();

    $this->actingAs($staf)
        ->post(route('organizer.events.announcements.publish', umumTesParam($setup, $draf)))
        ->assertForbidden();
});

it('umumTesGuestLoginDanBacaNotifikasiOwnOnly', function (): void {
    $setup = umumTesSetup();

    $this->get(route('announcements.index'))->assertRedirect(route('login'));
    $this->get(route('notifications.index'))->assertRedirect(route('login'));

    ['user' => $milik] = umumTesTugaskan($setup);
    ['user' => $orang] = umumTesTugaskan($setup);
    app(AnnouncementService::class)->publish(umumTesDraf($setup), $setup['owner']);

    $notifMilik = $milik->refresh()->unreadNotifications()->firstOrFail();
    $judulMilik = $notifMilik->data['title'] ?? '';
    $orang->refresh()->unreadNotifications()->firstOrFail();

    $this->actingAs($orang)
        ->post(route('notifications.read', $notifMilik->id))
        ->assertNotFound();

    $this->actingAs($milik)
        ->post(route('notifications.read', $notifMilik->id))
        ->assertRedirect(route('notifications.index'));

    expect($milik->refresh()->unreadNotifications()->count())->toBe(0)
        ->and($orang->refresh()->unreadNotifications()->count())->toBe(1);

    $this->actingAs($milik)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee($judulMilik);
});

/** @return array<string, mixed> */
function umumTesPayload(array $override = []): array
{
    return array_merge([
        'title' => 'Pengumuman '.Str::random(8),
        'body' => 'Isi pengumuman untuk relawan event ini.',
        'target_type' => 'event',
    ], $override);
}

it('umumTesTerbitUlangDitolak422TanpaNotifikasiBaru', function (): void {
    $setup = umumTesSetup();
    ['user' => $relawan] = umumTesTugaskan($setup);
    $draf = umumTesDraf($setup);

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.announcements.publish', umumTesParam($setup, $draf)))
        ->assertRedirect(route('organizer.events.announcements.show', umumTesParam($setup, $draf)));

    $jumlah = $relawan->refresh()->notifications()->count();
    expect($jumlah)->toBe(1);

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.announcements.publish', umumTesParam($setup, $draf)))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Pengumuman sudah diterbitkan.');

    expect($relawan->refresh()->notifications()->count())->toBe($jumlah)
        ->and(AuditLog::where('action', 'announcement.publish')->where('resource_id', (string) $draf->id)->count())->toBe(1);
});

it('umumTesSimpanDivisiAsingDitolak422', function (): void {
    $setup = umumTesSetup();
    $asing = umumTesSetup();

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.announcements.store', umumTesParam($setup)), umumTesPayload([
            'target_type' => 'division',
            'target_id' => $asing['division']->id,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_id');

    expect(Announcement::where('event_id', $setup['event']->id)->count())->toBe(0);
});

it('umumTesSimpanIndividuBelumDiterimaDitolak422', function (): void {
    $setup = umumTesSetup();
    $calon = User::factory()->create();
    Registration::factory()->create([
        'user_id' => $calon->id,
        'event_id' => $setup['event']->id,
        'role_id' => $setup['role']->id,
        'status' => 'pending',
    ]);

    $this->actingAs($setup['owner'])
        ->postJson(route('organizer.events.announcements.store', umumTesParam($setup)), umumTesPayload([
            'target_type' => 'individual',
            'target_id' => $calon->id,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_id');

    expect(Announcement::where('event_id', $setup['event']->id)->count())->toBe(0);
});

it('umumTesSimpanDrafValidTersimpan', function (): void {
    $setup = umumTesSetup();

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.announcements.store', umumTesParam($setup)), umumTesPayload([
            'target_type' => 'division',
            'target_id' => $setup['division']->id,
        ]))
        ->assertRedirect();

    $draf = Announcement::where('event_id', $setup['event']->id)->firstOrFail();
    expect($draf->target_type)->toBe('division')
        ->and((int) $draf->target_id)->toBe($setup['division']->id)
        ->and($draf->published_at)->toBeNull();
});

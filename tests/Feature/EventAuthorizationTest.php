<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\EventService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function eventAuthzBuatOrgDenganOwner(string $status = 'active'): array
{
    $org = Organization::factory()->create(['status' => $status]);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($owner->refresh(), $org);

    return [$org->refresh(), $owner->refresh()];
}

function eventAuthzTerimaStaf(Organization $org, User $owner, string $email): User
{
    $staf = User::factory()->create(['email' => $email]);
    $undangan = app(MembershipService::class)->invite($org, ['email' => $email], $owner);
    app(MembershipService::class)->acceptInvitation($undangan, $staf);

    return $staf->refresh();
}

function eventAuthzSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return $admin->refresh();
}

function eventAuthzEvent(User $owner, Organization $org, string $nama = 'Festival', string $slug = 'festival'): Event
{
    return app(EventService::class)->createEvent($org, [
        'name' => $nama,
        'slug' => $slug,
        'start_at' => now()->addMonth(),
        'end_at' => now()->addMonth()->addDays(2),
    ], $owner);
}

/** @return array<string, mixed> */
function eventAuthzPaket(): array
{
    [$org, $owner] = eventAuthzBuatOrgDenganOwner();
    $event = eventAuthzEvent($owner, $org);
    $svc = app(EventService::class);
    $div = $svc->createDivision($event, ['name' => 'Panggung'], $owner);
    $role = $svc->createRole($event, $div, ['name' => 'Usher', 'quota' => 5], $owner);
    $shift = $svc->createShift($event, $div, $role, [
        'start_at' => $event->start_at,
        'end_at' => $event->end_at,
    ], $owner);

    return ['org' => $org, 'owner' => $owner, 'event' => $event, 'div' => $div, 'role' => $role, 'shift' => $shift];
}

/** @return array<string, string> */
function eventAuthzPayloadEvent(): array
{
    return [
        'name' => 'Festival',
        'slug' => 'festival',
        'start_at' => now()->addMonth()->toDateTimeString(),
        'end_at' => now()->addMonth()->addDays(2)->toDateTimeString(),
    ];
}

/** @return array<string, int> */
function eventAuthzKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('tamu diarahkan ke login pada endpoint organizer dan melihat katalog publik', function (): void {
    $paket = eventAuthzPaket();
    $org = $paket['org'];
    $event = $paket['event'];

    $this->get(route('organizer.events.index', $org->slug))->assertRedirect('/login');
    $this->get(route('organizer.events.create', $org->slug))->assertRedirect('/login');
    $this->post(route('organizer.events.store', $org->slug), eventAuthzPayloadEvent())->assertRedirect('/login');
    $this->get(route('organizer.events.show', [$org->slug, $event->slug]))->assertRedirect('/login');
    $this->patch(route('organizer.events.update', [$org->slug, $event->slug]), ['name' => 'Coba'])->assertRedirect('/login');
    $this->get(route('organizer.events.divisions.index', [$org->slug, $event->slug]))->assertRedirect('/login');
    $this->get(route('organizer.events.roles.index', [$org->slug, $event->slug]))->assertRedirect('/login');
    $this->get(route('organizer.events.shifts.index', [$org->slug, $event->slug]))->assertRedirect('/login');
    $this->get(route('admin.events.index'))->assertRedirect(route('login'));

    $event->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $this->get(route('events.index'))->assertOk();
    $this->get(route('events.show', $event->slug))->assertOk();
});

it('owner mengelola penuh endpoint organizer event division role shift dan transisi', function (): void {
    $paket = eventAuthzPaket();
    $org = $paket['org'];
    $owner = $paket['owner'];
    $event = $paket['event'];
    $div = $paket['div'];
    $role = $paket['role'];
    $shift = $paket['shift'];

    $this->actingAs($owner)->get(route('organizer.events.index', $org->slug))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.create', $org->slug))->assertOk();
    $this->actingAs($owner)->post(route('organizer.events.store', $org->slug), [
        ...eventAuthzPayloadEvent(),
        'slug' => 'festival-owner',
        'name' => 'Festival Owner',
    ])->assertRedirect();
    $this->actingAs($owner)->get(route('organizer.events.show', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.edit', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($owner)->patch(route('organizer.events.update', [$org->slug, $event->slug]), [
        'name' => 'Festival Diperbarui',
    ])->assertRedirect();
    $this->actingAs($owner)->withSession(eventAuthzKonfirmasi())
        ->post(route('organizer.events.transition', [$org->slug, $event->slug]), ['status' => 'published'])
        ->assertRedirect();
    $this->actingAs($owner)->get(route('organizer.events.divisions.index', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.divisions.create', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.divisions.show', [$org->slug, $event->slug, $div->id]))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.roles.index', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.roles.create', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.roles.show', [$org->slug, $event->slug, $role->id]))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.shifts.index', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.shifts.create', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($owner)->get(route('organizer.events.shifts.show', [$org->slug, $event->slug, $shift->id]))->assertOk();
});

it('staf dengan permission event lolos endpoint terproteksi permission', function (): void {
    [$org, $owner] = eventAuthzBuatOrgDenganOwner();
    $event = eventAuthzEvent($owner, $org);
    $staf = eventAuthzTerimaStaf($org, $owner, 'staf-berperm@authz-event.test');
    $svc = app(MembershipService::class);
    foreach (['event.create', 'event.update', 'event.publish', 'division.manage', 'role.manage', 'shift.manage'] as $perm) {
        $svc->grantPermission($owner, $staf->refresh(), $org, $perm);
    }
    $staf = $staf->refresh();

    $this->actingAs($staf)->get(route('organizer.events.index', $org->slug))->assertOk();
    $this->actingAs($staf)->get(route('organizer.events.create', $org->slug))->assertOk();
    $this->actingAs($staf)->post(route('organizer.events.store', $org->slug), [
        ...eventAuthzPayloadEvent(),
        'slug' => 'festival-staf',
    ])->assertRedirect();
    $this->actingAs($staf)->get(route('organizer.events.show', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($staf)->patch(route('organizer.events.update', [$org->slug, $event->slug]), [
        'name' => 'Diperbarui Staf',
    ])->assertRedirect();
    $this->actingAs($staf)->withSession(eventAuthzKonfirmasi())
        ->post(route('organizer.events.transition', [$org->slug, $event->slug]), ['status' => 'published'])
        ->assertRedirect();
    $this->actingAs($staf)->get(route('organizer.events.divisions.index', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($staf)->post(route('organizer.events.divisions.store', [$org->slug, $event->slug]), [
        'name' => 'Logistik',
    ])->assertRedirect();
});

it('staf tanpa permission ditolak 403 pada operasi kelola tetapi boleh membaca dan melihat publik', function (): void {
    $paket = eventAuthzPaket();
    $org = $paket['org'];
    $event = $paket['event'];
    $div = $paket['div'];
    $staf = eventAuthzTerimaStaf($org, $paket['owner'], 'staf-tanpa-perm@authz-event.test');

    $this->actingAs($staf)->get(route('organizer.events.index', $org->slug))->assertOk();
    $this->actingAs($staf)->get(route('organizer.events.show', [$org->slug, $event->slug]))->assertOk();
    $this->actingAs($staf)->get(route('organizer.events.create', $org->slug))->assertForbidden();
    $this->actingAs($staf)->post(route('organizer.events.store', $org->slug), eventAuthzPayloadEvent())->assertForbidden();
    $this->actingAs($staf)->get(route('organizer.events.edit', [$org->slug, $event->slug]))->assertForbidden();
    $this->actingAs($staf)->patch(route('organizer.events.update', [$org->slug, $event->slug]), [
        'name' => 'Coba Ubah',
    ])->assertForbidden();
    $this->actingAs($staf)->get(route('organizer.events.divisions.index', [$org->slug, $event->slug]))->assertForbidden();
    $this->actingAs($staf)->post(route('organizer.events.divisions.store', [$org->slug, $event->slug]), [
        'name' => 'Gagal',
    ])->assertForbidden();
    $this->actingAs($staf)->get(route('organizer.events.divisions.show', [$org->slug, $event->slug, $div->id]))->assertForbidden();

    $event->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $this->actingAs($staf)->get(route('events.index'))->assertOk();
    $this->actingAs($staf)->get(route('events.show', $event->slug))->assertOk();
});

it('staf organisasi lain mendapat 404 pada resource event di luar scope', function (): void {
    $paketA = eventAuthzPaket();
    [$orgB, $ownerB] = eventAuthzBuatOrgDenganOwner();
    $stafB = eventAuthzTerimaStaf($orgB, $ownerB, 'staf-org-b@authz-event.test');
    $orgA = $paketA['org'];
    $eventA = $paketA['event'];
    $divA = $paketA['div'];
    $roleA = $paketA['role'];
    $shiftA = $paketA['shift'];

    $this->actingAs($stafB)->get(route('organizer.events.index', $orgA->slug))->assertNotFound();
    $this->actingAs($stafB)->get(route('organizer.events.show', [$orgA->slug, $eventA->slug]))->assertNotFound();
    $this->actingAs($stafB)->post(route('organizer.events.store', $orgA->slug), eventAuthzPayloadEvent())->assertNotFound();
    $this->actingAs($stafB)->patch(route('organizer.events.update', [$orgA->slug, $eventA->slug]), [
        'name' => 'Coba Ubah Silang',
    ])->assertNotFound();
    $this->actingAs($stafB)->get(route('organizer.events.divisions.index', [$orgA->slug, $eventA->slug]))->assertNotFound();
    $this->actingAs($stafB)->get(route('organizer.events.divisions.show', [$orgA->slug, $eventA->slug, $divA->id]))->assertNotFound();
    $this->actingAs($stafB)->get(route('organizer.events.roles.show', [$orgA->slug, $eventA->slug, $roleA->id]))->assertNotFound();
    $this->actingAs($stafB)->get(route('organizer.events.shifts.show', [$orgA->slug, $eventA->slug, $shiftA->id]))->assertNotFound();
});

it('volunteer tanpa membership ditolak 403 pada kelola organizer tetapi boleh katalog publik', function (): void {
    $paket = eventAuthzPaket();
    $org = $paket['org'];
    $event = $paket['event'];
    $volunteer = User::factory()->create();

    $this->actingAs($volunteer)->get(route('organizer.events.index', $org->slug))->assertNotFound();
    $this->actingAs($volunteer)->get(route('organizer.events.show', [$org->slug, $event->slug]))->assertNotFound();
    $this->actingAs($volunteer)->post(route('organizer.events.store', $org->slug), eventAuthzPayloadEvent())->assertNotFound();

    $event->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $this->actingAs($volunteer)->get(route('events.index'))->assertOk();
    $this->actingAs($volunteer)->get(route('events.show', $event->slug))->assertOk();
});

it('non-admin mendapat 403 pada panel event admin dan admin mengelola cancel', function (): void {
    $paket = eventAuthzPaket();
    $org = $paket['org'];
    $event = $paket['event'];
    $volunteer = User::factory()->create();
    $staf = eventAuthzTerimaStaf($org, $paket['owner'], 'staf-non-admin@authz-event.test');

    foreach ([$volunteer, $paket['owner'], $staf] as $aktor) {
        $this->actingAs($aktor)->get(route('admin.events.index'))->assertForbidden();
        $this->actingAs($aktor)->post(route('admin.events.cancel', $event->id), [
            'reason' => 'Coba batal.',
        ])->assertForbidden();
    }

    $admin = eventAuthzSuperAdmin();
    $this->actingAs($admin)->get(route('admin.events.index'))->assertOk();
    $this->actingAs($admin)->withSession(eventAuthzKonfirmasi())
        ->post(route('admin.events.cancel', $event->id), ['reason' => 'Darurat admin.'])
        ->assertRedirect(route('admin.events.index'));
    expect($event->fresh()->status)->toBe('cancelled');
});

it('hapus event hanya untuk owner sedangkan staf ber-permission update tetap ditolak', function (): void {
    $paket = eventAuthzPaket();
    $org = $paket['org'];
    $owner = $paket['owner'];
    $event = $paket['event'];
    $staf = eventAuthzTerimaStaf($org, $owner, 'staf-hapus@authz-event.test');
    app(MembershipService::class)->grantPermission($owner, $staf->refresh(), $org, 'event.update');
    $staf = $staf->refresh();

    $this->actingAs($staf)->delete(route('organizer.events.destroy', [$org->slug, $event->slug]))
        ->assertForbidden();
    $this->assertDatabaseHas('events', ['id' => $event->id]);

    $this->actingAs($owner)->delete(route('organizer.events.destroy', [$org->slug, $event->slug]))
        ->assertRedirect();
    expect(Event::find($event->id))->toBeNull();
});

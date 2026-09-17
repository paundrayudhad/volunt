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

function eventIsolasiBuatOrgDenganOwner(): array
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

    return [$org->refresh(), $owner->refresh()];
}

function eventIsolasiEvent(User $owner, Organization $org, string $nama, string $slug): Event
{
    return app(EventService::class)->createEvent($org, [
        'name' => $nama,
        'slug' => $slug,
        'start_at' => now()->addMonth(),
        'end_at' => now()->addMonth()->addDays(2),
    ], $owner);
}

/** @return array<string, mixed> */
function eventIsolasiPaket(Organization $org, User $owner, string $nama, string $slug): array
{
    $event = eventIsolasiEvent($owner, $org, $nama, $slug);
    $svc = app(EventService::class);
    $div = $svc->createDivision($event, ['name' => 'Panggung'], $owner);
    $role = $svc->createRole($event, $div, ['name' => 'Usher', 'quota' => 5], $owner);
    $shift = $svc->createShift($event, $div, $role, [
        'start_at' => $event->start_at,
        'end_at' => $event->end_at,
    ], $owner);

    return ['event' => $event, 'div' => $div, 'role' => $role, 'shift' => $shift];
}

/** @return array<string, mixed> */
function eventIsolasiPasangan(): array
{
    [$orgA, $ownerA] = eventIsolasiBuatOrgDenganOwner();
    [$orgB, $ownerB] = eventIsolasiBuatOrgDenganOwner();
    $paketA = eventIsolasiPaket($orgA, $ownerA, 'Festival A', 'festival-a');
    $paketB = eventIsolasiPaket($orgB, $ownerB, 'Festival B', 'festival-b');

    return ['orgA' => $orgA, 'ownerA' => $ownerA, 'orgB' => $orgB, 'ownerB' => $ownerB] + [
        'eventA' => $paketA['event'], 'divA' => $paketA['div'], 'roleA' => $paketA['role'], 'shiftA' => $paketA['shift'],
        'eventB' => $paketB['event'], 'divB' => $paketB['div'], 'roleB' => $paketB['role'], 'shiftB' => $paketB['shift'],
    ];
}

/** @return array<string, int> */
function eventIsolasiKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('read silang event menghasilkan 404 kedua arah', function (): void {
    $s = eventIsolasiPasangan();

    $this->actingAs($s['ownerA'])->get(route('organizer.events.show', [$s['orgB']->slug, $s['eventB']->slug]))->assertNotFound();
    $this->actingAs($s['ownerA'])->get(route('organizer.events.index', $s['orgB']->slug))->assertNotFound();
    $this->actingAs($s['ownerB'])->get(route('organizer.events.show', [$s['orgA']->slug, $s['eventA']->slug]))->assertNotFound();
    $this->actingAs($s['ownerB'])->get(route('organizer.events.index', $s['orgA']->slug))->assertNotFound();
});

it('update dan delete silang event menghasilkan 404 dan data utuh kedua arah', function (): void {
    $s = eventIsolasiPasangan();

    $this->actingAs($s['ownerA'])->patch(route('organizer.events.update', [$s['orgB']->slug, $s['eventB']->slug]), [
        'name' => 'Coba Ubah Silang',
    ])->assertNotFound();
    $this->actingAs($s['ownerA'])->delete(route('organizer.events.destroy', [$s['orgB']->slug, $s['eventB']->slug]))
        ->assertNotFound();

    $this->actingAs($s['ownerB'])->patch(route('organizer.events.update', [$s['orgA']->slug, $s['eventA']->slug]), [
        'name' => 'Coba Ubah Silang',
    ])->assertNotFound();
    $this->actingAs($s['ownerB'])->delete(route('organizer.events.destroy', [$s['orgA']->slug, $s['eventA']->slug]))
        ->assertNotFound();

    expect($s['eventA']->fresh()->name)->not->toBe('Coba Ubah Silang')
        ->and($s['eventB']->fresh()->name)->not->toBe('Coba Ubah Silang');
    $this->assertDatabaseHas('events', ['id' => $s['eventA']->id]);
    $this->assertDatabaseHas('events', ['id' => $s['eventB']->id]);
});

it('transisi silang menghasilkan 404 dan status tak berubah kedua arah', function (): void {
    $s = eventIsolasiPasangan();

    $this->actingAs($s['ownerA'])->withSession(eventIsolasiKonfirmasi())
        ->post(route('organizer.events.transition', [$s['orgB']->slug, $s['eventB']->slug]), ['status' => 'published'])
        ->assertNotFound();
    $this->actingAs($s['ownerB'])->withSession(eventIsolasiKonfirmasi())
        ->post(route('organizer.events.transition', [$s['orgA']->slug, $s['eventA']->slug]), ['status' => 'published'])
        ->assertNotFound();

    expect($s['eventA']->fresh()->status)->toBe('draft')
        ->and($s['eventB']->fresh()->status)->toBe('draft');
});

it('division role shift silang menghasilkan 404 kedua arah', function (): void {
    $s = eventIsolasiPasangan();

    $this->actingAs($s['ownerA'])->get(route('organizer.events.divisions.show', [$s['orgB']->slug, $s['eventB']->slug, $s['divB']->id]))->assertNotFound();
    $this->actingAs($s['ownerA'])->get(route('organizer.events.roles.show', [$s['orgB']->slug, $s['eventB']->slug, $s['roleB']->id]))->assertNotFound();
    $this->actingAs($s['ownerA'])->get(route('organizer.events.shifts.show', [$s['orgB']->slug, $s['eventB']->slug, $s['shiftB']->id]))->assertNotFound();
    $this->actingAs($s['ownerA'])->patch(route('organizer.events.divisions.update', [$s['orgB']->slug, $s['eventB']->slug, $s['divB']->id]), [
        'name' => 'Coba Ubah Silang',
    ])->assertNotFound();
    $this->actingAs($s['ownerA'])->delete(route('organizer.events.divisions.destroy', [$s['orgB']->slug, $s['eventB']->slug, $s['divB']->id]))->assertNotFound();

    $this->actingAs($s['ownerB'])->get(route('organizer.events.divisions.show', [$s['orgA']->slug, $s['eventA']->slug, $s['divA']->id]))->assertNotFound();
    $this->actingAs($s['ownerB'])->get(route('organizer.events.roles.show', [$s['orgA']->slug, $s['eventA']->slug, $s['roleA']->id]))->assertNotFound();
    $this->actingAs($s['ownerB'])->get(route('organizer.events.shifts.show', [$s['orgA']->slug, $s['eventA']->slug, $s['shiftA']->id]))->assertNotFound();
    $this->actingAs($s['ownerB'])->patch(route('organizer.events.roles.update', [$s['orgA']->slug, $s['eventA']->slug, $s['roleA']->id]), [
        'name' => 'Coba Ubah Silang',
    ])->assertNotFound();
    $this->actingAs($s['ownerB'])->delete(route('organizer.events.shifts.destroy', [$s['orgA']->slug, $s['eventA']->slug, $s['shiftA']->id]))->assertNotFound();

    expect($s['divA']->fresh()->name)->toBe('Panggung')
        ->and($s['divB']->fresh()->name)->toBe('Panggung');
    $this->assertDatabaseHas('event_divisions', ['id' => $s['divA']->id]);
    $this->assertDatabaseHas('event_divisions', ['id' => $s['divB']->id]);
});

it('id division role shift milik org lain dengan slug sendiri menghasilkan 404', function (): void {
    $s = eventIsolasiPasangan();

    $this->actingAs($s['ownerA'])->get(route('organizer.events.divisions.show', [$s['orgA']->slug, $s['eventA']->slug, $s['divB']->id]))->assertNotFound();
    $this->actingAs($s['ownerA'])->get(route('organizer.events.roles.show', [$s['orgA']->slug, $s['eventA']->slug, $s['roleB']->id]))->assertNotFound();
    $this->actingAs($s['ownerA'])->get(route('organizer.events.shifts.show', [$s['orgA']->slug, $s['eventA']->slug, $s['shiftB']->id]))->assertNotFound();

    $this->actingAs($s['ownerB'])->get(route('organizer.events.divisions.show', [$s['orgB']->slug, $s['eventB']->slug, $s['divA']->id]))->assertNotFound();
    $this->actingAs($s['ownerB'])->get(route('organizer.events.roles.show', [$s['orgB']->slug, $s['eventB']->slug, $s['roleA']->id]))->assertNotFound();
    $this->actingAs($s['ownerB'])->get(route('organizer.events.shifts.show', [$s['orgB']->slug, $s['eventB']->slug, $s['shiftA']->id]))->assertNotFound();
});

it('katalog publik hanya menampilkan event terbit milik kedua org tanpa bocor draf', function (): void {
    $s = eventIsolasiPasangan();
    $s['eventA']->forceFill(['status' => 'published', 'published_at' => now()])->save();

    $res = $this->get(route('events.index'));
    $res->assertOk()
        ->assertSee('Festival A')
        ->assertDontSee('Festival B');

    $this->get(route('events.show', $s['eventA']->slug))->assertOk();
    $this->get(route('events.show', $s['eventB']->slug))->assertNotFound();

    $s['eventB']->forceFill(['status' => 'cancelled'])->save();
    $this->get(route('events.show', $s['eventB']->slug))->assertNotFound();
});

it('volunteer luar ditolak pada resource event kedua organisasi tetapi boleh katalog', function (): void {
    $s = eventIsolasiPasangan();
    $volunteer = User::factory()->create();

    foreach ([$s['orgA'], $s['orgB']] as $org) {
        $this->actingAs($volunteer)->get(route('organizer.events.index', $org->slug))->assertNotFound();
    }
    $this->actingAs($volunteer)->get(route('organizer.events.show', [$s['orgA']->slug, $s['eventA']->slug]))->assertNotFound();
    $this->actingAs($volunteer)->get(route('organizer.events.show', [$s['orgB']->slug, $s['eventB']->slug]))->assertNotFound();
    $this->actingAs($volunteer)->post(route('organizer.events.store', $s['orgA']->slug), [
        'name' => 'Coba',
        'slug' => 'coba-volunteer',
        'start_at' => now()->addMonth()->toDateTimeString(),
        'end_at' => now()->addMonth()->addDays(2)->toDateTimeString(),
    ])->assertNotFound();
    $this->actingAs($volunteer)->get(route('organizer.events.divisions.show', [$s['orgB']->slug, $s['eventB']->slug, $s['divB']->id]))->assertNotFound();

    $s['eventA']->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $this->actingAs($volunteer)->get(route('events.index'))->assertOk();
    $this->actingAs($volunteer)->get(route('events.show', $s['eventA']->slug))->assertOk();
    $this->actingAs($volunteer)->get(route('events.show', $s['eventB']->slug))->assertNotFound();
});

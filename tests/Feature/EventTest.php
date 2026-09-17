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

function eventRuteOwner(): array
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

    return [$owner->refresh(), $org->refresh()];
}

function eventRuteStaf(Organization $org, User $owner, string $email): User
{
    $staf = User::factory()->create(['email' => $email]);
    $inv = app(MembershipService::class)->invite($org, ['email' => $email], $owner);
    app(MembershipService::class)->acceptInvitation($inv, $staf);

    return $staf->refresh();
}

function eventRuteEvent(User $owner, Organization $org, string $nama = 'Festival', string $slug = 'festival'): Event
{
    return app(EventService::class)->createEvent($org, [
        'name' => $nama,
        'slug' => $slug,
        'start_at' => now()->addMonth(),
        'end_at' => now()->addMonth()->addDays(2),
    ], $owner);
}

/** @return array<string, int> */
function eventRuteKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

/** @return array<string, string> */
function eventRutePayloadEvent(): array
{
    return [
        'name' => 'Festival',
        'slug' => 'festival',
        'start_at' => now()->addMonth()->toDateTimeString(),
        'end_at' => now()->addMonth()->addDays(2)->toDateTimeString(),
    ];
}

it('owner bisa membuat melihat mengubah transisi dan menghapus event', function (): void {
    [$owner, $org] = eventRuteOwner();

    $this->actingAs($owner)->post(route('organizer.events.store', $org->slug), eventRutePayloadEvent())
        ->assertRedirect();
    $event = Event::where('slug', 'festival')->firstOrFail();
    expect($event->organization_id)->toBe($org->id)
        ->and($event->status)->toBe('draft');

    $this->actingAs($owner)
        ->get(route('organizer.events.show', [$org->slug, $event->slug]))
        ->assertOk()
        ->assertSee('Festival');

    $this->actingAs($owner)
        ->patch(route('organizer.events.update', [$org->slug, $event->slug]), ['name' => 'Festival Baru'])
        ->assertRedirect();
    expect($event->fresh()->name)->toBe('Festival Baru');

    $this->actingAs($owner)->withSession(eventRuteKonfirmasi())
        ->post(route('organizer.events.transition', [$org->slug, $event->slug]), ['status' => 'published'])
        ->assertRedirect();
    expect($event->fresh()->status)->toBe('published');

    $this->actingAs($owner)
        ->delete(route('organizer.events.destroy', [$org->slug, $event->slug]))
        ->assertRedirect();
    expect(Event::find($event->id))->toBeNull()
        ->and(Event::withTrashed()->find($event->id))->not->toBeNull();
});

it('staf tanpa permission ditolak 403 dan staf organisasi lain 404', function (): void {
    [$owner, $org] = eventRuteOwner();
    $staf = eventRuteStaf($org, $owner, 'staf@example.com');

    $this->actingAs($staf->refresh())
        ->post(route('organizer.events.store', $org->slug), eventRutePayloadEvent())
        ->assertForbidden();

    [$ownerB, $orgB] = eventRuteOwner();
    $stafB = eventRuteStaf($orgB, $ownerB, 'stafb@example.com');

    $this->actingAs($stafB->refresh())
        ->get(route('organizer.events.index', $org->slug))
        ->assertNotFound();
    $this->actingAs($stafB->refresh())
        ->post(route('organizer.events.store', $org->slug), eventRutePayloadEvent())
        ->assertNotFound();
});

it('batal tanpa alasan ditolak transisi invalid ditolak dan tanpa konfirmasi dialihkan', function (): void {
    [$owner, $org] = eventRuteOwner();
    $event = eventRuteEvent($owner, $org);

    $this->actingAs($owner)
        ->post(route('organizer.events.transition', [$org->slug, $event->slug]), ['status' => 'published'])
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($owner)->withSession(eventRuteKonfirmasi())
        ->postJson(route('organizer.events.transition', [$org->slug, $event->slug]), ['status' => 'cancelled'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    $this->actingAs($owner)->withSession(eventRuteKonfirmasi())
        ->post(route('organizer.events.transition', [$org->slug, $event->slug]), ['status' => 'completed'])
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    $this->actingAs($owner)->withSession(eventRuteKonfirmasi())
        ->post(route('organizer.events.transition', [$org->slug, $event->slug]), [
            'status' => 'cancelled',
            'reason' => 'Sponsor mundur',
        ])->assertRedirect();
    expect($event->fresh()->status)->toBe('cancelled');
});

it('mass assignment organization_id dan status diabaikan', function (): void {
    [$owner, $org] = eventRuteOwner();
    [, $orgLain] = eventRuteOwner();

    $this->actingAs($owner)->post(route('organizer.events.store', $org->slug), [
        ...eventRutePayloadEvent(),
        'organization_id' => $orgLain->id,
        'status' => 'published',
    ])->assertRedirect();

    $event = Event::where('slug', 'festival')->firstOrFail();
    expect($event->organization_id)->toBe($org->id)
        ->and($event->status)->toBe('draft');
});

it('divisi role shift lintas event menghasilkan 404', function (): void {
    [$owner, $org] = eventRuteOwner();
    $svc = app(EventService::class);
    $eventA = eventRuteEvent($owner, $org, 'Acara A', 'acara-a');
    $eventB = eventRuteEvent($owner, $org, 'Acara B', 'acara-b');
    $div = $svc->createDivision($eventA, ['name' => 'Panggung'], $owner);
    $role = $svc->createRole($eventA, $div, ['name' => 'Usher', 'quota' => 5], $owner);
    $shift = $svc->createShift($eventA, $div, $role, [
        'start_at' => $eventA->start_at,
        'end_at' => $eventA->end_at,
    ], $owner);

    $this->actingAs($owner)
        ->get(route('organizer.events.divisions.show', [$org->slug, $eventB->slug, $div->id]))
        ->assertNotFound();
    $this->actingAs($owner)
        ->get(route('organizer.events.roles.show', [$org->slug, $eventB->slug, $role->id]))
        ->assertNotFound();
    $this->actingAs($owner)
        ->get(route('organizer.events.shifts.show', [$org->slug, $eventB->slug, $shift->id]))
        ->assertNotFound();
});

it('supervisor bukan anggota aktif ditolak 422', function (): void {
    [$owner, $org] = eventRuteOwner();
    $event = eventRuteEvent($owner, $org);
    $luar = User::factory()->create();

    $this->actingAs($owner)
        ->postJson(route('organizer.events.divisions.store', [$org->slug, $event->slug]), [
            'name' => 'Panggung',
            'supervisor_id' => $luar->id,
        ])->assertStatus(422)
        ->assertJsonValidationErrors('supervisor_id');

    $this->assertDatabaseMissing('event_divisions', ['event_id' => $event->id, 'name' => 'Panggung']);
});

it('role_id dari event lain ditolak 422', function (): void {
    [$owner, $org] = eventRuteOwner();
    $svc = app(EventService::class);
    $eventA = eventRuteEvent($owner, $org, 'Acara A', 'acara-a');
    $eventB = eventRuteEvent($owner, $org, 'Acara B', 'acara-b');
    $divA = $svc->createDivision($eventA, ['name' => 'Panggung'], $owner);
    $divB = $svc->createDivision($eventB, ['name' => 'Logistik'], $owner);
    $roleB = $svc->createRole($eventB, $divB, ['name' => 'Sopir', 'quota' => 2], $owner);

    $this->actingAs($owner)
        ->postJson(route('organizer.events.shifts.store', [$org->slug, $eventA->slug]), [
            'division_id' => $divA->id,
            'role_id' => $roleB->id,
            'start_at' => now()->addMonth()->toDateTimeString(),
            'end_at' => now()->addMonth()->addDay()->toDateTimeString(),
        ])->assertStatus(422)
        ->assertJsonValidationErrors('role_id');
});

it('staf dengan permission division.manage bisa membuat divisi', function (): void {
    [$owner, $org] = eventRuteOwner();
    $event = eventRuteEvent($owner, $org);
    $staf = eventRuteStaf($org, $owner, 'stafdiv@example.com');
    app(MembershipService::class)->grantPermission($owner, $staf->refresh(), $org, 'division.manage');

    $this->actingAs($staf->refresh())
        ->post(route('organizer.events.divisions.store', [$org->slug, $event->slug]), ['name' => 'Panggung'])
        ->assertRedirect();
    $this->assertDatabaseHas('event_divisions', ['event_id' => $event->id, 'name' => 'Panggung']);
});

it('tamu diarahkan ke login pada halaman event organizer', function (): void {
    [$owner, $org] = eventRuteOwner();
    eventRuteEvent($owner, $org);

    $this->get(route('organizer.events.index', $org->slug))->assertRedirect('/login');
});

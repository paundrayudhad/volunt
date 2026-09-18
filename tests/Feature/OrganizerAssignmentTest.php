<?php

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
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, role: EventRole, shift: EventShift} */
function tugasTesSetup(?int $kapasitas = null): array
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
        'start_at' => (clone $dasar)->setTime(10, 0),
        'end_at' => (clone $dasar)->setTime(14, 0),
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

function tugasTesRegistrasi(array $setup): Registration
{
    return Registration::factory()->create([
        'event_id' => $setup['event']->id,
        'role_id' => $setup['role']->id,
        'status' => 'accepted',
    ]);
}

function tugasTesShiftKedua(array $setup): EventShift
{
    $dasar = now()->addDays(6)->startOfDay();

    return EventShift::factory()->create([
        'event_id' => $setup['event']->id,
        'start_at' => (clone $dasar)->setTime(10, 0),
        'end_at' => (clone $dasar)->setTime(14, 0),
        'capacity' => null,
        'filled_count' => 0,
    ]);
}

/** @return array<string, mixed> */
function tugasTesKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('tugasTesDaftarMendukungFilterDanPaginasi', function (): void {
    $setup = tugasTesSetup();
    $pertama = null;
    foreach (range(1, 16) as $i) {
        $tugas = app(AssignmentService::class)->assign(tugasTesRegistrasi($setup), $setup['shift'], $setup['owner']);
        $pertama ??= $tugas;
    }
    app(AssignmentService::class)->confirm($pertama->refresh(), $setup['owner']);

    $response = $this->actingAs($setup['owner'])
        ->get(route('organizer.events.assignments.index', [$setup['org']->slug, $setup['event']->slug, 'status' => 'confirmed']));

    $response->assertOk()
        ->assertSee($pertama->refresh()->user->name);

    $page = $this->actingAs($setup['owner'])
        ->get(route('organizer.events.assignments.index', [$setup['org']->slug, $setup['event']->slug]));

    $page->assertOk();
    expect($page->viewData('assignments')->perPage())->toBe(15)
        ->and($page->viewData('assignments')->total())->toBe(16);
});

it('tugasTesAssignValidRedirectDanDb', function (): void {
    $setup = tugasTesSetup(5);
    $reg = tugasTesRegistrasi($setup);

    $this->actingAs($setup['owner'])->withSession(tugasTesKonfirmasi())
        ->post(route('organizer.events.assignments.assign', [$setup['org']->slug, $setup['event']->slug]), [
            'registration_id' => $reg->id,
            'shift_id' => $setup['shift']->id,
        ])
        ->assertRedirect();

    $tugas = Assignment::where('registration_id', $reg->id)->firstOrFail();
    expect($tugas->status)->toBe('assigned')
        ->and((int) $tugas->shift_id)->toBe($setup['shift']->id)
        ->and($setup['shift']->refresh()->filled_count)->toBe(1);
});

it('tugasTesAssignShiftPenuhErrorKuota', function (): void {
    $setup = tugasTesSetup(1);
    app(AssignmentService::class)->assign(tugasTesRegistrasi($setup), $setup['shift'], $setup['owner']);
    $penuh = tugasTesRegistrasi($setup);

    $this->actingAs($setup['owner'])->withSession(tugasTesKonfirmasi())
        ->from(route('organizer.events.assignments.index', [$setup['org']->slug, $setup['event']->slug]))
        ->post(route('organizer.events.assignments.assign', [$setup['org']->slug, $setup['event']->slug]), [
            'registration_id' => $penuh->id,
            'shift_id' => $setup['shift']->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect(Assignment::where('registration_id', $penuh->id)->exists())->toBeFalse()
        ->and($setup['shift']->refresh()->filled_count)->toBe(1);
});

it('tugasTesBulkTigaSukses', function (): void {
    $setup = tugasTesSetup(10);
    $ids = [];
    foreach (range(1, 3) as $i) {
        $ids[] = tugasTesRegistrasi($setup)->id;
    }

    $this->actingAs($setup['owner'])->withSession(tugasTesKonfirmasi())
        ->post(route('organizer.events.assignments.bulk', [$setup['org']->slug, $setup['event']->slug]), [
            'ids' => $ids,
            'shift_id' => $setup['shift']->id,
        ])
        ->assertRedirect(route('organizer.events.assignments.index', [$setup['org']->slug, $setup['event']->slug]));

    expect(Assignment::whereIn('registration_id', $ids)->where('status', 'assigned')->count())->toBe(3)
        ->and($setup['shift']->refresh()->filled_count)->toBe(3);
});

it('tugasTesBulkSatuIdLuarEvent404Rollback', function (): void {
    $setup = tugasTesSetup(10);
    $milik = tugasTesRegistrasi($setup);
    $lain = tugasTesSetup(10);
    $asing = tugasTesRegistrasi($lain);

    $this->actingAs($setup['owner'])->withSession(tugasTesKonfirmasi())
        ->postJson(route('organizer.events.assignments.bulk', [$setup['org']->slug, $setup['event']->slug]), [
            'ids' => [$milik->id, $asing->id],
            'shift_id' => $setup['shift']->id,
        ])
        ->assertNotFound();

    expect(Assignment::where('event_id', $setup['event']->id)->count())->toBe(0)
        ->and($setup['shift']->refresh()->filled_count)->toBe(0)
        ->and(Assignment::where('registration_id', $asing->id)->exists())->toBeFalse();
});

it('tugasTesStafReadOnly403Assign', function (): void {
    $setup = tugasTesSetup(5);
    $staf = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $setup['org']->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staf->refresh(), $setup['org']);
    $staf->refresh()->givePermissionTo('assignment.read');
    $reg = tugasTesRegistrasi($setup);

    $this->actingAs($staf->refresh())
        ->get(route('organizer.events.assignments.index', [$setup['org']->slug, $setup['event']->slug]))
        ->assertOk();

    $this->actingAs($staf->refresh())->withSession(tugasTesKonfirmasi())
        ->post(route('organizer.events.assignments.assign', [$setup['org']->slug, $setup['event']->slug]), [
            'registration_id' => $reg->id,
            'shift_id' => $setup['shift']->id,
        ])
        ->assertForbidden();

    expect(Assignment::where('registration_id', $reg->id)->exists())->toBeFalse();
});

it('tugasTesLintasEvent404', function (): void {
    $setup = tugasTesSetup();
    $lain = tugasTesSetup();
    $tugas = app(AssignmentService::class)->assign(tugasTesRegistrasi($lain), $lain['shift'], $lain['owner']);

    $this->actingAs($setup['owner'])
        ->get(route('organizer.events.assignments.show', [$setup['org']->slug, $setup['event']->slug, $tugas->id]))
        ->assertNotFound();
});

it('tugasTesGuestLogin', function (): void {
    $setup = tugasTesSetup();
    $tugas = app(AssignmentService::class)->assign(tugasTesRegistrasi($setup), $setup['shift'], $setup['owner']);

    $this->get(route('organizer.events.assignments.index', [$setup['org']->slug, $setup['event']->slug]))
        ->assertRedirect(route('login'));
    $this->get(route('organizer.events.assignments.show', [$setup['org']->slug, $setup['event']->slug, $tugas->id]))
        ->assertRedirect(route('login'));
});

it('tugasTesReassignConfirmCancelHappyPath', function (): void {
    $setup = tugasTesSetup(5);
    $reg = tugasTesRegistrasi($setup);
    $tugas = app(AssignmentService::class)->assign($reg, $setup['shift'], $setup['owner']);
    $baru = tugasTesShiftKedua($setup);

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.assignments.reassign', [$setup['org']->slug, $setup['event']->slug, $tugas->id]), [
            'shift_id' => $baru->id,
        ])
        ->assertRedirect();

    expect($tugas->refresh()->status)->toBe('reassigned')
        ->and((int) $tugas->refresh()->shift_id)->toBe($baru->id)
        ->and($setup['shift']->refresh()->filled_count)->toBe(0)
        ->and($baru->refresh()->filled_count)->toBe(1);

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.assignments.confirm', [$setup['org']->slug, $setup['event']->slug, $tugas->id]))
        ->assertRedirect();

    expect($tugas->refresh()->status)->toBe('confirmed');

    $this->actingAs($setup['owner'])
        ->post(route('organizer.events.assignments.cancel', [$setup['org']->slug, $setup['event']->slug, $tugas->id]), [
            'reason' => 'Volunteer berhalangan.',
        ])
        ->assertRedirect();

    expect($tugas->refresh()->status)->toBe('cancelled')
        ->and($baru->refresh()->filled_count)->toBe(0);
});

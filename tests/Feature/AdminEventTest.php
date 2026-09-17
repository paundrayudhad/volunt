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

function adminEventSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return $admin->refresh();
}

function adminEventOwner(): array
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

function adminEventBuat(User $owner, Organization $org, string $nama, string $slug): Event
{
    return app(EventService::class)->createEvent($org, [
        'name' => $nama,
        'slug' => $slug,
        'start_at' => now()->addMonth(),
        'end_at' => now()->addMonth()->addDays(2),
    ], $owner);
}

function adminEventKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('tamu diarahkan ke login pada panel event admin', function (): void {
    $this->get(route('admin.events.index'))->assertRedirect(route('login'));
});

it('super_admin melihat daftar semua event lintas organisasi', function (): void {
    $admin = adminEventSuperAdmin();
    [$ownerA, $orgA] = adminEventOwner();
    [$ownerB, $orgB] = adminEventOwner();
    adminEventBuat($ownerA, $orgA, 'Festival A', 'festival-a');
    adminEventBuat($ownerB, $orgB, 'Festival B', 'festival-b');

    $this->actingAs($admin)->get(route('admin.events.index'))
        ->assertOk()
        ->assertSee('Kelola Event')
        ->assertSee('Festival A')
        ->assertSee('Festival B')
        ->assertSee($orgA->name)
        ->assertSee($orgB->name);
});

it('daftar admin mendukung filter status dan paginasi 20 per halaman', function (): void {
    $admin = adminEventSuperAdmin();
    [$owner, $org] = adminEventOwner();
    for ($i = 1; $i <= 21; $i++) {
        adminEventBuat($owner, $org, "Event {$i}", "event-{$i}");
    }
    $terbit = Event::where('slug', 'event-21')->firstOrFail();
    app(EventService::class)->transitionTo($terbit, 'published', $owner);

    $this->actingAs($admin)->get(route('admin.events.index', ['status' => 'published']))
        ->assertOk()
        ->assertSee('Event 21')
        ->assertDontSee('Event 20');

    $this->actingAs($admin)->get(route('admin.events.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('Event 1')
        ->assertDontSee('Event 21');
});

it('cancel paksa tanpa alasan mengembalikan 422', function (): void {
    $admin = adminEventSuperAdmin();
    [$owner, $org] = adminEventOwner();
    $event = adminEventBuat($owner, $org, 'Festival Batal', 'festival-batal');

    $this->actingAs($admin)->withSession(adminEventKonfirmasi())
        ->postJson(route('admin.events.cancel', $event->id), [])
        ->assertStatus(422);

    expect($event->fresh()->status)->toBe('draft');
});

it('cancel paksa dengan alasan membatalkan event dan mencatat audit', function (): void {
    $admin = adminEventSuperAdmin();
    [$owner, $org] = adminEventOwner();
    $event = adminEventBuat($owner, $org, 'Festival Darurat', 'festival-darurat');

    $this->actingAs($admin)->withSession(adminEventKonfirmasi())
        ->post(route('admin.events.cancel', $event->id), ['reason' => 'Cuaca ekstrem.'])
        ->assertRedirect(route('admin.events.index'));

    expect($event->fresh()->status)->toBe('cancelled');

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'event.cancelled',
    ]);
});

it('cancel paksa tanpa konfirmasi password dialihkan ke halaman konfirmasi', function (): void {
    $admin = adminEventSuperAdmin();
    [$owner, $org] = adminEventOwner();
    $event = adminEventBuat($owner, $org, 'Festival Reauth', 'festival-reauth');

    $this->actingAs($admin)
        ->post(route('admin.events.cancel', $event->id), ['reason' => 'Alasan.'])
        ->assertRedirectToRoute('password.confirm');

    expect($event->fresh()->status)->toBe('draft');
});

it('non-admin tidak bisa mengakses panel event admin', function (): void {
    [$owner, $org] = adminEventOwner();
    $event = adminEventBuat($owner, $org, 'Festival Umum', 'festival-umum');
    $biasa = User::factory()->create();

    $this->actingAs($biasa)->get(route('admin.events.index'))->assertForbidden();
    $this->actingAs($biasa)
        ->post(route('admin.events.cancel', $event->id), ['reason' => 'Alasan.'])
        ->assertForbidden();
    $this->actingAs($owner)->get(route('admin.events.index'))->assertForbidden();
    $this->actingAs($owner)
        ->post(route('admin.events.cancel', $event->id), ['reason' => 'Alasan.'])
        ->assertForbidden();
});

it('sync permission owner mencakup delapan permission event baru', function (): void {
    [$owner] = adminEventOwner();

    foreach (['event.create', 'event.view', 'event.update', 'event.delete', 'event.publish', 'division.manage', 'role.manage', 'shift.manage'] as $perm) {
        expect($owner->can($perm))->toBeTrue();
    }
});

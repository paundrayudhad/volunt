<?php

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function adminRegSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return $admin->refresh();
}

/** @return array{org: Organization, owner: User, event: Event} */
function adminRegSetup(): array
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
    $event->forceFill(['status' => 'registration_open'])->save();
    $division = EventDivision::factory()->create(['event_id' => $event->id]);
    EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'quota' => 5,
        'accepted_count' => 0,
    ]);

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
    ];
}

function adminRegDaftar(array $setup, string $status = 'pending'): Registration
{
    $role = $setup['event']->roles()->firstOrFail();

    return Registration::unguarded(fn (): Registration => Registration::create([
        'user_id' => User::factory()->create()->id,
        'event_id' => $setup['event']->id,
        'role_id' => $role->id,
        'status' => $status,
        'submitted_at' => now(),
        'idempotency_key' => (string) Str::uuid(),
    ]))->refresh();
}

it('tamu diarahkan ke login pada panel pendaftaran admin', function (): void {
    $setup = adminRegSetup();
    $reg = adminRegDaftar($setup);

    $this->get(route('admin.registrations.index'))->assertRedirect(route('login'));
    $this->get(route('admin.registrations.show', $reg->id))->assertRedirect(route('login'));
});

it('super_admin melihat pendaftaran lintas organisasi', function (): void {
    $admin = adminRegSuperAdmin();
    $setupA = adminRegSetup();
    $setupB = adminRegSetup();
    $regA = adminRegDaftar($setupA);
    $regB = adminRegDaftar($setupB);

    $this->actingAs($admin)->get(route('admin.registrations.index'))
        ->assertOk()
        ->assertSee('Kelola Pendaftaran')
        ->assertSee($regA->user->name)
        ->assertSee($regB->user->name)
        ->assertSee($setupA['org']->name)
        ->assertSee($setupB['org']->name);
});

it('daftar admin mendukung filter status dan organisasi', function (): void {
    $admin = adminRegSuperAdmin();
    $setup = adminRegSetup();
    $lain = adminRegSetup();
    $pending = adminRegDaftar($setup, 'pending');
    $diterima = adminRegDaftar($setup, 'accepted');
    $tetangga = adminRegDaftar($lain, 'pending');

    $this->actingAs($admin)->get(route('admin.registrations.index', ['status' => 'accepted']))
        ->assertOk()
        ->assertSee($diterima->user->name)
        ->assertDontSee($pending->user->name);

    $this->actingAs($admin)->get(route('admin.registrations.index', ['org' => $lain['org']->id]))
        ->assertOk()
        ->assertSee($tetangga->user->name)
        ->assertDontSee($pending->user->name);
});

it('non-admin tidak bisa mengakses panel pendaftaran admin', function (): void {
    $setup = adminRegSetup();
    $reg = adminRegDaftar($setup);
    $biasa = User::factory()->create();

    $this->actingAs($biasa)->get(route('admin.registrations.index'))->assertForbidden();
    $this->actingAs($biasa)->get(route('admin.registrations.show', $reg->id))->assertForbidden();
    $this->actingAs($setup['owner'])->get(route('admin.registrations.index'))->assertForbidden();
});

it('owner mendapat permission registration.review via sync', function (): void {
    $setup = adminRegSetup();

    expect($setup['owner']->can('registration.read'))->toBeTrue()
        ->and($setup['owner']->can('registration.review'))->toBeTrue();
});

it('halaman detail admin read-only tanpa tombol mutasi', function (): void {
    $admin = adminRegSuperAdmin();
    $setup = adminRegSetup();
    $reg = adminRegDaftar($setup);

    $this->actingAs($admin)->get(route('admin.registrations.show', $reg->id))
        ->assertOk()
        ->assertSee($reg->user->name)
        ->assertSee($setup['event']->name)
        ->assertDontSee('Simpan keputusan')
        ->assertDontSee('Terapkan seleksi massal');
});

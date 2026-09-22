<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, relawan: User, event: Event} */
function blArtisPaket(): array
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
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $relawan = User::factory()->create();
    $peran = EventRole::factory()->create(['event_id' => $event->id]);
    Registration::factory()->create([
        'user_id' => $relawan->id,
        'event_id' => $event->id,
        'role_id' => $peran->id,
        'status' => 'accepted',
    ]);

    return ['org' => $org->refresh(), 'owner' => $owner->refresh(), 'relawan' => $relawan->refresh(), 'event' => $event->refresh()];
}

it('backfill memberi artist.manage ke owner lama', function () {
    $paket = blArtisPaket();
    $paket['owner']->revokePermissionTo(['artist.manage', 'artist.liaise', 'artist.read']);
    expect($paket['owner']->refresh()->can('artist.manage'))->toBeFalse();

    (require base_path('database/migrations/2026_09_22_000003_backfill_artist_permissions.php'))->up();

    expect($paket['owner']->refresh()->can('artist.manage'))->toBeTrue()
        ->and($paket['owner']->can('artist.liaise'))->toBeTrue();
});

it('backfill memberi artist.liaise ke volunteer accepted', function () {
    $paket = blArtisPaket();
    $paket['relawan']->revokePermissionTo('artist.liaise');
    expect($paket['relawan']->refresh()->can('artist.liaise'))->toBeFalse();

    (require base_path('database/migrations/2026_09_22_000003_backfill_artist_permissions.php'))->up();

    expect($paket['relawan']->refresh()->can('artist.liaise'))->toBeTrue()
        ->and($paket['relawan']->can('artist.manage'))->toBeFalse();
});

it('backfill tak memberi perm ke user biasa', function () {
    $paket = blArtisPaket();
    $biasa = User::factory()->create();

    (require base_path('database/migrations/2026_09_22_000003_backfill_artist_permissions.php'))->up();

    expect($biasa->refresh()->can('artist.manage'))->toBeFalse()
        ->and($biasa->can('artist.liaise'))->toBeFalse()
        ->and($paket['owner']->refresh()->can('artist.manage'))->toBeTrue()
        ->and($paket['relawan']->refresh()->can('artist.liaise'))->toBeTrue();
});

it('up() membuat permission yang hilang lalu backfill', function () {
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    Permission::whereIn('name', ['artist.manage', 'artist.liaise'])->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    (require base_path('database/migrations/2026_09_22_000003_backfill_artist_permissions.php'))->up();

    expect($owner->refresh()->can('artist.manage'))->toBeTrue()
        ->and($owner->can('artist.liaise'))->toBeTrue();
});

it('down() tak mencabut artist.liaise milik volunteer accepted; non-member kehilangan semua', function () {
    $paket = blArtisPaket();
    $paket['owner']->givePermissionTo(['artist.manage', 'artist.liaise']);
    $paket['relawan']->givePermissionTo('artist.liaise');
    $luar = User::factory()->create();
    $luar->givePermissionTo(['artist.manage', 'artist.liaise']);

    (require base_path('database/migrations/2026_09_22_000003_backfill_artist_permissions.php'))->down();

    expect($paket['owner']->refresh()->can('artist.manage'))->toBeTrue()
        ->and($paket['relawan']->refresh()->can('artist.liaise'))->toBeTrue()
        ->and($luar->refresh()->can('artist.manage'))->toBeFalse()
        ->and($luar->can('artist.liaise'))->toBeFalse();
});

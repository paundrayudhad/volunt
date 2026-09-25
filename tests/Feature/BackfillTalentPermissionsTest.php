<?php

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('backfill memberi talent.search dan talent.invite ke owner lama', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    $owner->revokePermissionTo(['talent.search', 'talent.invite']);
    expect($owner->refresh()->can('talent.search'))->toBeFalse()
        ->and($owner->can('talent.invite'))->toBeFalse();

    (require base_path('database/migrations/2026_09_23_000002_backfill_talent_permissions.php'))->up();

    expect($owner->refresh()->can('talent.search'))->toBeTrue()
        ->and($owner->can('talent.invite'))->toBeTrue();
});

it('backfill tidak memberi permission ke user biasa yang bukan owner', function () {
    $biasa = User::factory()->create();

    (require base_path('database/migrations/2026_09_23_000002_backfill_talent_permissions.php'))->up();

    expect($biasa->refresh()->can('talent.search'))->toBeFalse()
        ->and($biasa->can('talent.invite'))->toBeFalse();
});

it('up() membuat permission yang hilang lalu backfill', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    Permission::whereIn('name', ['talent.search', 'talent.invite'])->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    (require base_path('database/migrations/2026_09_23_000002_backfill_talent_permissions.php'))->up();

    expect($owner->refresh()->can('talent.search'))->toBeTrue()
        ->and($owner->can('talent.invite'))->toBeTrue();
});

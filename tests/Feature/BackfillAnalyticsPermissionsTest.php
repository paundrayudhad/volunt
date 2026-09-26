<?php

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('backfill memberi analytics.view dan analytics.export ke owner lama', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();

    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    $migration = require database_path('migrations/2026_09_26_000001_backfill_analytics_permissions.php');
    $migration->up();

    expect($owner->fresh()->hasPermissionTo('analytics.view'))->toBeTrue();
    expect($owner->fresh()->hasPermissionTo('analytics.export'))->toBeTrue();
});

it('backfill tidak memberi permission ke user biasa yang bukan owner', function () {
    $nonOwner = User::factory()->create();

    $migration = require database_path('migrations/2026_09_26_000001_backfill_analytics_permissions.php');
    $migration->up();

    expect($nonOwner->fresh()->hasPermissionTo('analytics.view'))->toBeFalse();
    expect($nonOwner->fresh()->hasPermissionTo('analytics.export'))->toBeFalse();
});

it('up() membuat permission yang hilang lalu backfill', function () {
    Permission::query()->delete();

    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();

    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    $migration = require database_path('migrations/2026_09_26_000001_backfill_analytics_permissions.php');
    $migration->up();

    expect(Permission::where('name', 'analytics.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'analytics.export')->exists())->toBeTrue();
    expect($owner->fresh()->hasPermissionTo('analytics.view'))->toBeTrue();
    expect($owner->fresh()->hasPermissionTo('analytics.export'))->toBeTrue();
});

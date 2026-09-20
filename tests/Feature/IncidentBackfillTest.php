<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('backfill memberi perm insiden ke owner lama', function () {
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $owner->givePermissionTo(['incident.manage', 'incident.report', 'lostfound.manage']);
    Event::factory()->create(['organization_id' => $org->id]);
    $owner->revokePermissionTo(['incident.manage', 'incident.report', 'lostfound.manage']);
    expect($owner->refresh()->can('incident.manage'))->toBeFalse();

    (require base_path('database/migrations/2026_09_20_000006_backfill_incident_permissions.php'))->up();

    expect($owner->refresh()->can('incident.manage'))->toBeTrue()
        ->and($owner->can('incident.report'))->toBeTrue()
        ->and($owner->can('lostfound.manage'))->toBeTrue();
});

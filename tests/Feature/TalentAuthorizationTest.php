<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('talent.search', 'web');
    Permission::findOrCreate('talent.invite', 'web');
});

it('member tanpa permission talent.search tidak bisa mengakses talent pool', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $staff = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    $response = $this->actingAs($staff)->get(route('organizer.talent.index', $org->slug));
    $response->assertStatus(403);
});

it('owner atau member dengan permission talent.search dapat melihat talent pool', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $owner->givePermissionTo(['talent.search']);

    $response = $this->actingAs($owner)->get(route('organizer.talent.index', $org->slug));
    $response->assertStatus(200);
});

it('member tanpa permission talent.invite tidak bisa mengirim undangan', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $staff = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'published']);
    $volunteer = User::factory()->create();

    $response = $this->actingAs($staff)->post(route('organizer.events.talent.invite', [$org->slug, $event->slug]), [
        'user_id' => $volunteer->id,
    ]);

    $response->assertStatus(403);
});

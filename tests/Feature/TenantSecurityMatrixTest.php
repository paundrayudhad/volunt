<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('memastikan isolasi data lintas organisasi menghasilkan 404 pada seluruh modul utama', function () {
    $orgA = Organization::factory()->create(['status' => 'active']);
    $orgB = Organization::factory()->create(['status' => 'active']);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $orgA->id,
        'user_id' => $ownerA->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $orgB->id,
        'user_id' => $ownerB->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    app(MembershipService::class)->syncPermissions($ownerA->refresh(), $orgA);
    app(MembershipService::class)->syncPermissions($ownerB->refresh(), $orgB);

    $ownerA->givePermissionTo(Permission::all());
    $ownerB->givePermissionTo(Permission::all());

    $eventA = Event::factory()->create(['organization_id' => $orgA->id, 'status' => 'published']);
    $eventB = Event::factory()->create(['organization_id' => $orgB->id, 'status' => 'published']);

    // 1. Event show silang
    $this->actingAs($ownerA)->get(route('organizer.events.show', [$orgA->slug, $eventB->slug]))->assertStatus(404);
    $this->actingAs($ownerB)->get(route('organizer.events.show', [$orgB->slug, $eventA->slug]))->assertStatus(404);

    // 2. Analytics silang
    $this->actingAs($ownerA)->get(route('organizer.events.analytics.index', [$orgA->slug, $eventB->slug]))->assertStatus(404);
    $this->actingAs($ownerB)->get(route('organizer.events.analytics.index', [$orgB->slug, $eventA->slug]))->assertStatus(404);

    // 3. Export silang
    $this->actingAs($ownerA)->get(route('organizer.events.export', [$orgA->slug, $eventB->slug, 'registrations']))->assertStatus(404);
    $this->actingAs($ownerB)->get(route('organizer.events.export', [$orgB->slug, $eventA->slug, 'registrations']))->assertStatus(404);
});

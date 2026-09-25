<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Services\TalentPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('talent.search', 'web');
    Permission::findOrCreate('talent.invite', 'web');
});

it('organizer dapat mencari relawan dari riwayat event miliknya', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $owner->givePermissionTo(['talent.search', 'talent.invite']);

    $volunteer = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    VolunteerProfile::unguarded(fn () => VolunteerProfile::create([
        'user_id' => $volunteer->id,
        'full_name' => 'Budi Santoso',
        'skills' => ['Design', 'Fotografi'],
        'city' => 'Jakarta Selatan',
        'visibility' => 'organizers_only',
    ]));

    $event = Event::factory()->create(['organization_id' => $org->id]);
    $role = EventRole::factory()->create(['event_id' => $event->id]);

    Registration::unguarded(fn () => Registration::create([
        'user_id' => $volunteer->id,
        'event_id' => $event->id,
        'role_id' => $role->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $service = app(TalentPoolService::class);
    $results = $service->search($org, $owner, ['q' => 'Budi']);

    expect($results->total())->toBe(1);
    expect($results->items()[0]->id)->toBe($volunteer->id);
});

it('relawan dengan visibility private disembunyikan dari talent pool', function () {
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

    $volunteer = User::factory()->create(['name' => 'Siti Rahma']);
    VolunteerProfile::unguarded(fn () => VolunteerProfile::create([
        'user_id' => $volunteer->id,
        'full_name' => 'Siti Rahma',
        'skills' => ['Logistik'],
        'city' => 'Bandung',
        'visibility' => 'private',
    ]));

    $event = Event::factory()->create(['organization_id' => $org->id]);
    $role = EventRole::factory()->create(['event_id' => $event->id]);

    Registration::unguarded(fn () => Registration::create([
        'user_id' => $volunteer->id,
        'event_id' => $event->id,
        'role_id' => $role->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $service = app(TalentPoolService::class);
    $results = $service->search($org, $owner, ['q' => 'Siti']);

    expect($results->total())->toBe(0);
});

it('organizer lain tidak dapat menemukan relawan yang tidak pernah mendaftar di organisasinya', function () {
    $orgA = Organization::factory()->create(['status' => 'active']);
    $orgB = Organization::factory()->create(['status' => 'active']);

    $ownerB = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $orgB->id,
        'user_id' => $ownerB->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $ownerB->givePermissionTo(['talent.search']);

    $volunteer = User::factory()->create(['name' => 'Andi Pratama']);
    VolunteerProfile::unguarded(fn () => VolunteerProfile::create([
        'user_id' => $volunteer->id,
        'full_name' => 'Andi Pratama',
        'skills' => ['Audio Visual'],
        'city' => 'Surabaya',
        'visibility' => 'public',
    ]));

    $eventA = Event::factory()->create(['organization_id' => $orgA->id]);
    $roleA = EventRole::factory()->create(['event_id' => $eventA->id]);

    Registration::unguarded(fn () => Registration::create([
        'user_id' => $volunteer->id,
        'event_id' => $eventA->id,
        'role_id' => $roleA->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $service = app(TalentPoolService::class);
    $results = $service->search($orgB, $ownerB, ['q' => 'Andi']);

    expect($results->total())->toBe(0);
});

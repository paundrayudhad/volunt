<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\OrganizationAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menghitung metrik portofolio organisasi dengan benar', function () {
    $org1 = Organization::factory()->create(['status' => 'active']);
    $org2 = Organization::factory()->create(['status' => 'active']);

    $event1 = Event::factory()->create(['organization_id' => $org1->id, 'status' => 'completed']);
    $event2 = Event::factory()->create(['organization_id' => $org1->id, 'status' => 'published']);
    $eventLain = Event::factory()->create(['organization_id' => $org2->id, 'status' => 'completed']);

    $role1 = EventRole::factory()->create(['event_id' => $event1->id]);
    $role2 = EventRole::factory()->create(['event_id' => $event2->id]);

    $vol1 = User::factory()->create();
    $vol2 = User::factory()->create();

    Registration::unguarded(fn () => Registration::create([
        'event_id' => $event1->id,
        'user_id' => $vol1->id,
        'role_id' => $role1->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    Registration::unguarded(fn () => Registration::create([
        'event_id' => $event2->id,
        'user_id' => $vol1->id,
        'role_id' => $role2->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    Registration::unguarded(fn () => Registration::create([
        'event_id' => $event2->id,
        'user_id' => $vol2->id,
        'role_id' => $role2->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $service = app(OrganizationAnalyticsService::class);
    $portfolio = $service->getOrganizationSummary($org1);

    expect($portfolio['total_events'])->toBe(2);
    expect($portfolio['completed_events'])->toBe(1);
    expect($portfolio['active_events'])->toBe(1);
    expect($portfolio['unique_volunteers_count'])->toBe(2);
    expect($portfolio['total_accepted_registrations'])->toBe(3);
});

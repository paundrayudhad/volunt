<?php

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\EventAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('analytics.view', 'web');
    Permission::findOrCreate('analytics.export', 'web');
});

it('menghitung metrik analitik event dengan benar', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'published']);

    $div1 = EventDivision::factory()->create(['event_id' => $event->id]);
    $role1 = EventRole::factory()->create(['event_id' => $event->id, 'division_id' => $div1->id, 'quota' => 10, 'accepted_count' => 2]);
    $shift1 = EventShift::factory()->create(['event_id' => $event->id, 'capacity' => 5, 'filled_count' => 2]);

    $vol1 = User::factory()->create();
    $vol2 = User::factory()->create();
    $vol3 = User::factory()->create();

    $reg1 = Registration::unguarded(fn () => Registration::create([
        'event_id' => $event->id,
        'user_id' => $vol1->id,
        'role_id' => $role1->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $reg2 = Registration::unguarded(fn () => Registration::create([
        'event_id' => $event->id,
        'user_id' => $vol2->id,
        'role_id' => $role1->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $reg3 = Registration::unguarded(fn () => Registration::create([
        'event_id' => $event->id,
        'user_id' => $vol3->id,
        'role_id' => $role1->id,
        'status' => 'rejected',
        'idempotency_key' => fake()->uuid(),
    ]));

    $assign1 = Assignment::unguarded(fn () => Assignment::create([
        'registration_id' => $reg1->id,
        'user_id' => $vol1->id,
        'event_id' => $event->id,
        'division_id' => $div1->id,
        'role_id' => $role1->id,
        'shift_id' => $shift1->id,
        'status' => 'confirmed',
    ]));

    $assign2 = Assignment::unguarded(fn () => Assignment::create([
        'registration_id' => $reg2->id,
        'user_id' => $vol2->id,
        'event_id' => $event->id,
        'division_id' => $div1->id,
        'role_id' => $role1->id,
        'shift_id' => $shift1->id,
        'status' => 'confirmed',
    ]));

    Attendance::unguarded(fn () => Attendance::create([
        'assignment_id' => $assign1->id,
        'shift_id' => $shift1->id,
        'event_id' => $event->id,
        'user_id' => $vol1->id,
        'checked_in_at' => now(),
        'method' => 'qr',
        'status' => 'present',
        'idempotency_key' => fake()->uuid(),
    ]));

    Attendance::unguarded(fn () => Attendance::create([
        'assignment_id' => $assign2->id,
        'shift_id' => $shift1->id,
        'event_id' => $event->id,
        'user_id' => $vol2->id,
        'checked_in_at' => now(),
        'method' => 'manual',
        'status' => 'late',
        'idempotency_key' => fake()->uuid(),
    ]));

    Incident::unguarded(fn () => Incident::create([
        'event_id' => $event->id,
        'reporter_id' => $vol1->id,
        'category' => 'technical',
        'location' => 'Panggung Utama',
        'description' => 'Bahaya di area panggung',
        'priority' => 'critical',
        'status' => 'open',
    ]));

    $service = app(EventAnalyticsService::class);
    $summary = $service->getEventSummary($event);

    expect($summary['registration_funnel']['total'])->toBe(3);
    expect($summary['registration_funnel']['accepted'])->toBe(2);
    expect($summary['registration_funnel']['rejected'])->toBe(1);

    expect($summary['attendance_summary']['total_assignments'])->toBe(2);
    expect($summary['attendance_summary']['present'])->toBe(1);
    expect($summary['attendance_summary']['late'])->toBe(1);
    expect($summary['attendance_summary']['attendance_rate_pct'])->toBe(100.0);

    expect($summary['incident_summary']['total'])->toBe(1);
    expect($summary['incident_summary']['critical_count'])->toBe(1);
});

it('memastikan isolasi data analitik antar event', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $eventA = Event::factory()->create(['organization_id' => $org->id]);
    $eventB = Event::factory()->create(['organization_id' => $org->id]);

    $roleA = EventRole::factory()->create(['event_id' => $eventA->id]);
    $roleB = EventRole::factory()->create(['event_id' => $eventB->id]);

    $vol = User::factory()->create();
    Registration::unguarded(fn () => Registration::create([
        'event_id' => $eventA->id,
        'user_id' => $vol->id,
        'role_id' => $roleA->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $service = app(EventAnalyticsService::class);
    $summaryA = $service->getEventSummary($eventA);
    $summaryB = $service->getEventSummary($eventB);

    expect($summaryA['registration_funnel']['accepted'])->toBe(1);
    expect($summaryB['registration_funnel']['accepted'])->toBe(0);
});

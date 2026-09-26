<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\DataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('analytics.view', 'web');
    Permission::findOrCreate('analytics.export', 'web');
});

it('dapat mengekspor dataset registrasi dalam format CSV', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'published']);
    $role = EventRole::factory()->create(['event_id' => $event->id]);

    $vol = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    Registration::unguarded(fn () => Registration::create([
        'event_id' => $event->id,
        'user_id' => $vol->id,
        'role_id' => $role->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $service = app(DataExportService::class);
    $response = $service->exportCsv($event, 'registrations', $owner);

    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('.csv');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Budi Santoso');
    expect($content)->toContain('budi@example.com');
});

it('dapat mengekspor dataset registrasi dalam format XLSX', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'published']);

    $service = app(DataExportService::class);
    $response = $service->exportXlsx($event, 'registrations', $owner);

    expect($response->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

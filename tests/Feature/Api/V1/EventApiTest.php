<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hanya menampilkan event yang berstatus published pada list publik', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $published = Event::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Event Publik Terbuka',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    $draft = Event::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Event Internal Draft',
        'status' => 'draft',
    ]);

    $response = $this->getJson('/api/v1/events');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonPath('data.0.name', 'Event Publik Terbuka');
});

it('dapat menampilkan detail event publik beserta role dan shift', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);
    EventRole::factory()->create([
        'event_id' => $event->id,
        'name' => 'Liaison Officer',
        'quota' => 10,
    ]);
    EventShift::factory()->create([
        'event_id' => $event->id,
        'location' => 'Panggung Utama',
        'capacity' => 5,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(4),
    ]);

    $response = $this->getJson('/api/v1/events/'.$event->slug);

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $event->id)
        ->assertJsonPath('data.roles.0.name', 'Liaison Officer')
        ->assertJsonPath('data.shifts.0.location', 'Panggung Utama')
        ->assertJsonPath('data.shifts.0.capacity', 5);
});

it('mengembalikan 404 pada event yang masih draft atau tidak ditemukan', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $draft = Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'draft',
    ]);

    $response = $this->getJson('/api/v1/events/'.$draft->slug);

    $response->assertStatus(404);
});

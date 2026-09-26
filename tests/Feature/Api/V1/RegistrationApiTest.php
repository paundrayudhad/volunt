<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('dapat mendaftar sebagai volunteer pada event publik melalui API', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'registration_open',
        'registration_start_at' => now()->subDay(),
        'registration_end_at' => now()->addDays(5),
    ]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'name' => 'Logistik',
        'quota' => 5,
    ]);

    $idempotencyKey = Str::uuid()->toString();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/events/'.$event->slug.'/register', [
        'event_role_id' => $role->id,
        'answers' => [],
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.event.name', $event->name);

    $this->assertDatabaseHas('registrations', [
        'user_id' => $user->id,
        'event_id' => $event->id,
        'role_id' => $role->id,
    ]);
});

it('dapat melihat riwayat pendaftaran milik sendiri', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $role = EventRole::factory()->create(['event_id' => $event->id]);

    $myReg = Registration::unguarded(fn () => Registration::create([
        'user_id' => $user->id, 'event_id' => $event->id, 'role_id' => $role->id, 'status' => 'pending', 'submitted_at' => now(), 'idempotency_key' => Str::uuid()->toString(),
    ]));

    Registration::unguarded(fn () => Registration::create([
        'user_id' => $otherUser->id, 'event_id' => $event->id, 'role_id' => $role->id, 'status' => 'pending', 'submitted_at' => now(), 'idempotency_key' => Str::uuid()->toString(),
    ]));

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/my/registrations');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $myReg->id);
});

it('dapat membatalkan pendaftaran pending milik sendiri dan 404 jika milik orang lain', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'registration_open']);
    $role = EventRole::factory()->create(['event_id' => $event->id]);

    $reg = Registration::unguarded(fn () => Registration::create([
        'user_id' => $user->id, 'event_id' => $event->id, 'role_id' => $role->id, 'status' => 'pending', 'submitted_at' => now(), 'idempotency_key' => Str::uuid()->toString(),
    ]));

    // Coba batalkan milik orang lain -> 404 fail-closed
    $this->actingAs($otherUser, 'sanctum')
        ->postJson('/api/v1/my/registrations/'.$reg->id.'/withdraw')
        ->assertStatus(404);

    // Batalkan milik sendiri -> 200 OK
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/my/registrations/'.$reg->id.'/withdraw')
        ->assertStatus(200);

    expect($reg->fresh()->status)->toBe('withdrawn');
});

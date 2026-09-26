<?php

use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');
});

it('superadmin dapat mengakses daftar seluruh sertifikat lintas organisasi', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $org = Organization::factory()->create(['status' => 'active']);
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $role = EventRole::factory()->create(['event_id' => $event->id]);
    $vol = User::factory()->create();

    $reg = Registration::unguarded(fn () => Registration::create([
        'event_id' => $event->id,
        'user_id' => $vol->id,
        'role_id' => $role->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    Certificate::unguarded(fn () => Certificate::create([
        'event_id' => $event->id,
        'user_id' => $vol->id,
        'registration_id' => $reg->id,
        'certificate_no' => 'WV-2026-TEST01',
        'qr_token_hash' => hash('sha256', 'token1'),
        'issued_at' => now(),
    ]));

    $response = $this->actingAs($admin)->get(route('admin.certificates.index'));
    $response->assertStatus(200);
    $response->assertSee('WV-2026-TEST01');
});

it('non-admin ditolak 403 saat mengakses daftar sertifikat admin', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.certificates.index'));
    $response->assertStatus(403);
});

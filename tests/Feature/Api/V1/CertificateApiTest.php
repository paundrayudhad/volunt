<?php

use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('memverifikasi sertifikat yang valid dan mengembalikan data publik minimal tanpa PII', function () {
    $org = Organization::factory()->create(['status' => 'active', 'name' => 'Organisasi Sukarelawan']);
    $event = Event::factory()->create(['organization_id' => $org->id, 'name' => 'Bakti Sosial']);
    $role = EventRole::factory()->create(['event_id' => $event->id, 'name' => 'Koordinator Lapangan']);
    $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi.secret@example.com']);

    $reg = Registration::unguarded(fn () => Registration::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'role_id' => $role->id,
        'status' => 'accepted',
        'submitted_at' => now(),
        'idempotency_key' => Str::uuid()->toString(),
    ]));

    Certificate::unguarded(fn () => Certificate::create([
        'certificate_no' => 'CERT-2026-999',
        'event_id' => $event->id,
        'user_id' => $user->id,
        'registration_id' => $reg->id,
        'qr_token_hash' => hash('sha256', 'dummy-token'),
        'issued_at' => now(),
    ]));

    $response = $this->getJson('/api/v1/certificates/verify/CERT-2026-999');

    $response->assertStatus(200)
        ->assertJson([
            'valid' => true,
            'certificate' => [
                'certificate_no' => 'CERT-2026-999',
                'recipient_name' => 'Budi Santoso',
                'event_name' => 'Bakti Sosial',
                'organization_name' => 'Organisasi Sukarelawan',
                'role_name' => 'Koordinator Lapangan',
            ],
        ]);

    // Pastikan email tidak bocor
    expect($response->getContent())->not->toContain('budi.secret@example.com');
});

it('mengembalikan 404 pada nomor sertifikat yang tidak ditemukan atau berstatus revoked', function () {
    $response = $this->getJson('/api/v1/certificates/verify/CERT-INVALID-000');

    $response->assertStatus(404)
        ->assertJson(['valid' => false, 'message' => 'Sertifikat tidak valid atau tidak ditemukan.']);
});

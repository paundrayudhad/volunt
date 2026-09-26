<?php

use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('certificate.read', 'web');
});

it('organizer dapat memfilter sertifikat berdasarkan status valid dan revoked', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $owner->givePermissionTo('certificate.read');

    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'published']);
    $role = EventRole::factory()->create(['event_id' => $event->id]);
    $vol1 = User::factory()->create();
    $vol2 = User::factory()->create();

    $reg1 = Registration::unguarded(fn () => Registration::create([
        'event_id' => $event->id,
        'user_id' => $vol1->id,
        'role_id' => $role->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $reg2 = Registration::unguarded(fn () => Registration::create([
        'event_id' => $event->id,
        'user_id' => $vol2->id,
        'role_id' => $role->id,
        'status' => 'accepted',
        'idempotency_key' => fake()->uuid(),
    ]));

    $validCert = Certificate::unguarded(fn () => Certificate::create([
        'event_id' => $event->id,
        'user_id' => $vol1->id,
        'registration_id' => $reg1->id,
        'certificate_no' => 'WV-2026-VAL001',
        'qr_token_hash' => hash('sha256', 'tok1'),
        'issued_at' => now(),
    ]));

    $revokedCert = Certificate::unguarded(fn () => Certificate::create([
        'event_id' => $event->id,
        'user_id' => $vol2->id,
        'registration_id' => $reg2->id,
        'certificate_no' => 'WV-2026-REV002',
        'qr_token_hash' => hash('sha256', 'tok2'),
        'issued_at' => now(),
        'revoked_at' => now(),
        'revoke_reason' => 'Pelanggaran kode etik',
    ]));

    $resValid = $this->actingAs($owner)->get(route('organizer.events.certificates.index', [$org->slug, $event->slug, 'status' => 'valid']));
    $resValid->assertStatus(200);
    $resValid->assertSee('WV-2026-VAL001');
    $resValid->assertDontSee('WV-2026-REV002');

    $resRevoked = $this->actingAs($owner)->get(route('organizer.events.certificates.index', [$org->slug, $event->slug, 'status' => 'revoked']));
    $resRevoked->assertStatus(200);
    $resRevoked->assertSee('WV-2026-REV002');
    $resRevoked->assertDontSee('WV-2026-VAL001');
});

<?php

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\CertificateVerification;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\CertificateService;
use App\Services\EventService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, divisi: EventDivision, role: EventRole} */
function sertIsolasiPaket(): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($owner->refresh(), $org);

    $event = Event::factory()->create(['organization_id' => $org->id]);
    $divisi = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $divisi->id,
        'quota' => 50,
        'accepted_count' => 0,
    ]);

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'divisi' => $divisi->refresh(),
        'role' => $role->refresh(),
    ];
}

function sertIsolasiSelesaikan(array $s): Event
{
    $svc = app(EventService::class);
    $event = $s['event']->refresh();
    foreach (['published', 'registration_open', 'registration_closed', 'ongoing', 'completed'] as $berikut) {
        $event = $svc->transitionTo($event, $berikut, $s['owner']);
    }

    return $event->refresh();
}

/** @return array{relawan: User, sertifikat: Certificate} */
function sertIsolasiTerbit(array $s, Event $event): array
{
    $relawan = User::factory()->create();
    $reg = Registration::factory()->create([
        'user_id' => $relawan->id,
        'event_id' => $s['event']->id,
        'role_id' => $s['role']->id,
        'status' => 'accepted',
    ]);
    $shift = EventShift::factory()->create([
        'event_id' => $s['event']->id,
        'division_id' => $s['divisi']->id,
        'role_id' => $s['role']->id,
        'start_at' => now()->subDays(4),
        'end_at' => now()->subDays(3),
        'capacity' => 50,
        'filled_count' => 0,
    ]);
    $tugas = app(AssignmentService::class)->assign($reg, $shift, $s['owner']);
    app(AssignmentService::class)->confirm($tugas->refresh(), $s['owner']);
    Attendance::unguarded(fn (): Attendance => Attendance::create([
        'assignment_id' => $tugas->id,
        'shift_id' => $tugas->shift_id,
        'event_id' => $tugas->event_id,
        'user_id' => $tugas->user_id,
        'checked_in_at' => now(),
        'method' => 'manual',
        'status' => 'present',
        'idempotency_key' => (string) Str::uuid(),
    ]));
    $sertifikat = app(CertificateService::class)->issueBatch($event->refresh(), $s['owner'])['issued'][0];

    return ['relawan' => $relawan->refresh(), 'sertifikat' => $sertifikat->refresh()];
}

/** @return array<string, mixed> */
function sertIsolasiKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('sertTesIsolasiDuaOrgSimetris', function (): void {
    $paketA = sertIsolasiPaket();
    $eventA = sertIsolasiSelesaikan($paketA);
    $hasilA = sertIsolasiTerbit($paketA, $eventA);
    $paketB = sertIsolasiPaket();
    $eventB = sertIsolasiSelesaikan($paketB);
    $hasilB = sertIsolasiTerbit($paketB, $eventB);

    $sertA = $hasilA['sertifikat']->refresh();
    $sertB = $hasilB['sertifikat']->refresh();
    $relawanA = $hasilA['relawan']->refresh();
    $relawanB = $hasilB['relawan']->refresh();

    expect($sertA->certificate_no)->not->toBe($sertB->certificate_no)
        ->and(Certificate::where('event_id', $eventA->id)->count())->toBe(1)
        ->and(Certificate::where('event_id', $eventB->id)->count())->toBe(1);

    $this->actingAs($relawanA)->get(route('my.certificates.index'))
        ->assertOk()
        ->assertSee($sertA->certificate_no)
        ->assertDontSee($sertB->certificate_no);
    $this->actingAs($relawanB)->get(route('my.certificates.index'))
        ->assertOk()
        ->assertSee($sertB->certificate_no)
        ->assertDontSee($sertA->certificate_no);

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.certificates.show', [$paketA['org']->slug, $paketA['event']->slug, $sertB->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.certificates.show', [$paketB['org']->slug, $paketB['event']->slug, $sertA->id]))
        ->assertNotFound();

    $this->actingAs($paketA['owner'])->withSession(sertIsolasiKonfirmasi())
        ->post(route('organizer.events.certificates.revoke', [$paketA['org']->slug, $paketA['event']->slug, $sertB->id]), [
            'reason' => 'Alasan pencabutan yang cukup panjang.',
        ])
        ->assertNotFound();
    $this->actingAs($paketB['owner'])->withSession(sertIsolasiKonfirmasi())
        ->post(route('organizer.events.certificates.revoke', [$paketB['org']->slug, $paketB['event']->slug, $sertA->id]), [
            'reason' => 'Alasan pencabutan yang cukup panjang.',
        ])
        ->assertNotFound();

    $this->actingAs($relawanA)
        ->get(route('my.certificates.download', $sertB->id))
        ->assertNotFound();
    $this->actingAs($relawanB)
        ->get(route('my.certificates.download', $sertA->id))
        ->assertNotFound();

    $this->get(route('certificates.verify', $sertB->certificate_no))
        ->assertOk()
        ->assertSee($sertB->certificate_no)
        ->assertSee($relawanB->name)
        ->assertSee('VALID');

    expect($sertA->refresh()->revoked_at)->toBeNull()
        ->and($sertB->refresh()->revoked_at)->toBeNull()
        ->and(Assignment::where('event_id', $eventA->id)->count())->toBe(1)
        ->and(Assignment::where('event_id', $eventB->id)->count())->toBe(1)
        ->and(CertificateVerification::where('certificate_id', $sertB->id)->count())->toBe(1);
});

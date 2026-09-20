<?php

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Certificate;
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
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, divisi: EventDivision, role: EventRole} */
function sertWebSetup(): array
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

function sertWebSelesaikanEvent(array $s): Event
{
    $svc = app(EventService::class);
    $event = $s['event']->refresh();
    foreach (['published', 'registration_open', 'registration_closed', 'ongoing', 'completed'] as $berikut) {
        $event = $svc->transitionTo($event, $berikut, $s['owner']);
    }

    return $event->refresh();
}

function sertWebRegistrasi(array $s, ?User $relawan = null): Registration
{
    return Registration::factory()->create([
        'user_id' => ($relawan ?? User::factory()->create())->id,
        'event_id' => $s['event']->id,
        'role_id' => $s['role']->id,
        'status' => 'accepted',
    ]);
}

function sertWebShift(array $s, Carbon $mulai, Carbon $selesai): EventShift
{
    return EventShift::factory()->create([
        'event_id' => $s['event']->id,
        'division_id' => $s['divisi']->id,
        'role_id' => $s['role']->id,
        'start_at' => $mulai,
        'end_at' => $selesai,
        'capacity' => 50,
        'filled_count' => 0,
    ]);
}

function sertWebTugaskan(array $s, Registration $reg, EventShift $shift): Assignment
{
    $tugas = app(AssignmentService::class)->assign($reg, $shift, $s['owner']);
    app(AssignmentService::class)->confirm($tugas->refresh(), $s['owner']);

    return $tugas->refresh();
}

function sertWebHadir(Assignment $tugas, string $status = 'present'): Attendance
{
    return Attendance::unguarded(fn (): Attendance => Attendance::create([
        'assignment_id' => $tugas->id,
        'shift_id' => $tugas->shift_id,
        'event_id' => $tugas->event_id,
        'user_id' => $tugas->user_id,
        'checked_in_at' => now(),
        'method' => 'manual',
        'status' => $status,
        'idempotency_key' => (string) Str::uuid(),
    ]));
}

/** @return array<string, mixed> */
function sertWebKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

function sertWebStafReadOnly(array $s): User
{
    $staf = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $s['org']->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staf->refresh(), $s['org']);
    $staf->refresh()->givePermissionTo('certificate.read');

    return $staf->refresh();
}

/** @return array<string, mixed> */
function sertWebParam(array $s, ?Certificate $sertifikat = null): array
{
    $param = [$s['org']->slug, $s['event']->slug];
    if ($sertifikat instanceof Certificate) {
        $param[] = $sertifikat->id;
    }

    return $param;
}

it('sertTesOrganizerTerbitBatch', function (): void {
    $s = sertWebSetup();
    $event = sertWebSelesaikanEvent($s);
    $relawanA = User::factory()->create();
    $relawanB = User::factory()->create();
    $relawanC = User::factory()->create();
    $tugasA = sertWebTugaskan($s, sertWebRegistrasi($s, $relawanA), sertWebShift($s, now()->subDays(4), now()->subDays(3)));
    $tugasB = sertWebTugaskan($s, sertWebRegistrasi($s, $relawanB), sertWebShift($s, now()->subDays(4), now()->subDays(3)));
    $tugasC = sertWebTugaskan($s, sertWebRegistrasi($s, $relawanC), sertWebShift($s, now()->subDays(4), now()->subDays(3)));
    sertWebHadir($tugasA);
    sertWebHadir($tugasB);

    $this->actingAs($s['owner'])->withSession(sertWebKonfirmasi())
        ->post(route('organizer.events.certificates.issue', sertWebParam($s)))
        ->assertRedirect(route('organizer.events.certificates.index', sertWebParam($s)))
        ->assertSessionHas('status', '2 sertifikat diterbitkan, 1 dilewati.');

    expect(Certificate::where('event_id', $event->id)->count())->toBe(2)
        ->and($tugasA->refresh()->status)->toBe('completed')
        ->and($tugasB->refresh()->status)->toBe('completed')
        ->and($tugasC->refresh()->status)->toBe('completed');

    $this->actingAs($s['owner'])
        ->get(route('organizer.events.certificates.index', sertWebParam($s)))
        ->assertOk()
        ->assertSee('2 sertifikat diterbitkan, 1 dilewati.');
    expect($this->get(route('organizer.events.certificates.index', sertWebParam($s)))->viewData('ambang'))->toBe(50);

    $terbit = Certificate::where('event_id', $event->id)->firstOrFail();
    $this->actingAs($s['owner'])
        ->get(route('organizer.events.certificates.show', sertWebParam($s, $terbit)))
        ->assertOk()
        ->assertSee($terbit->certificate_no);
});

it('sertTesStafReadOnly403Mutasi', function (): void {
    $s = sertWebSetup();
    $event = sertWebSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $tugas = sertWebTugaskan($s, sertWebRegistrasi($s, $relawan), sertWebShift($s, now()->subDays(4), now()->subDays(3)));
    sertWebHadir($tugas);
    $sertifikat = app(CertificateService::class)->issueBatch($event->refresh(), $s['owner'])['issued'][0];
    $staf = sertWebStafReadOnly($s);

    $this->actingAs($staf)
        ->get(route('organizer.events.certificates.index', sertWebParam($s)))
        ->assertOk();

    $this->actingAs($staf)->withSession(sertWebKonfirmasi())
        ->post(route('organizer.events.certificates.issue', sertWebParam($s)))
        ->assertForbidden();

    $this->actingAs($staf)->withSession(sertWebKonfirmasi())
        ->post(route('organizer.events.certificates.revoke', sertWebParam($s, $sertifikat)), [
            'reason' => 'Alasan pencabutan yang cukup panjang.',
        ])
        ->assertForbidden();

    expect($sertifikat->refresh()->isRevoked())->toBeFalse()
        ->and(Certificate::where('event_id', $event->id)->count())->toBe(1);
});

it('sertTesLintasEvent404', function (): void {
    $sA = sertWebSetup();
    $sB = sertWebSetup();
    $regB = Registration::factory()->create([
        'user_id' => User::factory()->create()->id,
        'event_id' => $sB['event']->id,
        'role_id' => $sB['role']->id,
        'status' => 'accepted',
    ]);
    $sertifikatB = Certificate::unguarded(fn (): Certificate => Certificate::create([
        'event_id' => $sB['event']->id,
        'user_id' => $regB->user_id,
        'registration_id' => $regB->id,
        'certificate_no' => 'WV-'.now()->format('Y').'-BEDA01',
        'issued_at' => now(),
        'qr_token_hash' => hash('sha256', Str::random(64)),
    ]));

    $this->actingAs($sA['owner'])
        ->get(route('organizer.events.certificates.show', [$sA['org']->slug, $sA['event']->slug, $sertifikatB->id]))
        ->assertNotFound();
});

it('sertTesAmbangInvalid422', function (): void {
    $s = sertWebSetup();
    sertWebSelesaikanEvent($s);

    foreach ([0, 101] as $ambang) {
        $this->actingAs($s['owner'])->withSession(sertWebKonfirmasi())
            ->from(route('organizer.events.certificates.index', sertWebParam($s)))
            ->post(route('organizer.events.certificates.issue', sertWebParam($s)), [
                'min_attendance_pct' => $ambang,
            ])
            ->assertRedirect(route('organizer.events.certificates.index', sertWebParam($s)))
            ->assertSessionHasErrors('min_attendance_pct');
    }

    expect(Certificate::where('event_id', $s['event']->id)->count())->toBe(0);
});

it('sertTesRevokeAlasanPendek422', function (): void {
    $s = sertWebSetup();
    $event = sertWebSelesaikanEvent($s);
    $relawan = User::factory()->create();
    $tugas = sertWebTugaskan($s, sertWebRegistrasi($s, $relawan), sertWebShift($s, now()->subDays(4), now()->subDays(3)));
    sertWebHadir($tugas);
    $sertifikat = app(CertificateService::class)->issueBatch($event->refresh(), $s['owner'])['issued'][0];

    $this->actingAs($s['owner'])->withSession(sertWebKonfirmasi())
        ->from(route('organizer.events.certificates.show', sertWebParam($s, $sertifikat)))
        ->post(route('organizer.events.certificates.revoke', sertWebParam($s, $sertifikat)), [
            'reason' => 'Alasan 9!',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('reason');

    expect($sertifikat->refresh()->isRevoked())->toBeFalse();
});

it('sertTesIssueEventOngoingDitolak', function (): void {
    $s = sertWebSetup();
    $svc = app(EventService::class);
    $event = $s['event']->refresh();
    foreach (['published', 'registration_open', 'registration_closed', 'ongoing'] as $berikut) {
        $event = $svc->transitionTo($event, $berikut, $s['owner']);
    }

    $this->actingAs($s['owner'])->withSession(sertWebKonfirmasi())
        ->from(route('organizer.events.certificates.index', sertWebParam($s)))
        ->post(route('organizer.events.certificates.issue', sertWebParam($s)))
        ->assertRedirect(route('organizer.events.certificates.index', sertWebParam($s)))
        ->assertSessionHasErrors('threshold');

    expect(Certificate::where('event_id', $event->id)->count())->toBe(0);
});

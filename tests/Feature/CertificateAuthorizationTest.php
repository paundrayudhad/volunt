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
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, divisi: EventDivision, role: EventRole} */
function sertMatriksPaket(): array
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

function sertMatriksSelesaikan(array $s): Event
{
    $svc = app(EventService::class);
    $event = $s['event']->refresh();
    foreach (['published', 'registration_open', 'registration_closed', 'ongoing', 'completed'] as $berikut) {
        $event = $svc->transitionTo($event, $berikut, $s['owner']);
    }

    return $event->refresh();
}

function sertMatriksDaftar(array $s, ?User $relawan = null): Registration
{
    return Registration::factory()->create([
        'user_id' => ($relawan ?? User::factory()->create())->id,
        'event_id' => $s['event']->id,
        'role_id' => $s['role']->id,
        'status' => 'accepted',
    ]);
}

function sertMatriksShift(array $s): EventShift
{
    return EventShift::factory()->create([
        'event_id' => $s['event']->id,
        'division_id' => $s['divisi']->id,
        'role_id' => $s['role']->id,
        'start_at' => now()->subDays(4),
        'end_at' => now()->subDays(3),
        'capacity' => 50,
        'filled_count' => 0,
    ]);
}

function sertMatriksTugas(array $s, Registration $reg, EventShift $shift): Assignment
{
    $tugas = app(AssignmentService::class)->assign($reg, $shift, $s['owner']);
    app(AssignmentService::class)->confirm($tugas->refresh(), $s['owner']);

    return $tugas->refresh();
}

function sertMatriksHadir(Assignment $tugas): Attendance
{
    return Attendance::unguarded(fn (): Attendance => Attendance::create([
        'assignment_id' => $tugas->id,
        'shift_id' => $tugas->shift_id,
        'event_id' => $tugas->event_id,
        'user_id' => $tugas->user_id,
        'checked_in_at' => now(),
        'method' => 'manual',
        'status' => 'present',
        'idempotency_key' => (string) Str::uuid(),
    ]));
}

function sertMatriksStafReadOnly(array $s): User
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

/** @return array{sertifikat: Certificate, relawan: User} */
function sertMatriksTerbit(array $s, Event $event): array
{
    $relawan = User::factory()->create();
    $tugas = sertMatriksTugas($s, sertMatriksDaftar($s, $relawan), sertMatriksShift($s));
    sertMatriksHadir($tugas);
    $terbit = app(CertificateService::class)->issueBatch($event->refresh(), $s['owner'])['issued'][0];

    return ['sertifikat' => $terbit->refresh(), 'relawan' => $relawan->refresh()];
}

/** @return array<string, mixed> */
function sertMatriksKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

/** @return array{0: string, 1: string} */
function sertMatriksParam(array $s): array
{
    return [$s['org']->slug, $s['event']->slug];
}

it('sertTesGuestDiarahkanKeLogin', function (): void {
    $s = sertMatriksPaket();
    $event = sertMatriksSelesaikan($s);
    ['sertifikat' => $sertifikat] = sertMatriksTerbit($s, $event);
    [$org, $slug] = sertMatriksParam($s);

    $this->get(route('organizer.events.certificates.index', [$org, $slug]))->assertRedirect(route('login'));
    $this->get(route('organizer.events.certificates.show', [$org, $slug, $sertifikat->id]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.certificates.issue', [$org, $slug]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.certificates.revoke', [$org, $slug, $sertifikat->id]), [
        'reason' => 'Alasan pencabutan yang cukup panjang.',
    ])->assertRedirect(route('login'));

    $this->get(route('my.certificates.index'))->assertRedirect(route('login'));
    $this->get(route('my.certificates.download', $sertifikat->id))->assertRedirect(route('login'));

    $this->get(route('certificates.verify', $sertifikat->certificate_no))->assertOk()->assertSee('VALID');
    $this->get(route('certificates.verify', 'WV-2026-TIDAKADA'))->assertNotFound();
});

it('sertTesStafReadOnly403SemuaMutasi', function (): void {
    $s = sertMatriksPaket();
    $event = sertMatriksSelesaikan($s);
    ['sertifikat' => $sertifikat] = sertMatriksTerbit($s, $event);
    $staf = sertMatriksStafReadOnly($s);
    [$org, $slug] = sertMatriksParam($s);

    $this->actingAs($staf)
        ->get(route('organizer.events.certificates.index', [$org, $slug]))
        ->assertOk();
    $this->actingAs($staf)
        ->get(route('organizer.events.certificates.show', [$org, $slug, $sertifikat->id]))
        ->assertOk()
        ->assertSee($sertifikat->certificate_no);

    $this->actingAs($staf)->withSession(sertMatriksKonfirmasi())
        ->post(route('organizer.events.certificates.issue', [$org, $slug]))
        ->assertForbidden();
    $this->actingAs($staf)->withSession(sertMatriksKonfirmasi())
        ->post(route('organizer.events.certificates.revoke', [$org, $slug, $sertifikat->id]), [
            'reason' => 'Alasan pencabutan yang cukup panjang.',
        ])
        ->assertForbidden();

    expect($sertifikat->refresh()->revoked_at)->toBeNull()
        ->and(Certificate::where('event_id', $event->id)->count())->toBe(1);
});

it('sertTesLintasOrgEvent404', function (): void {
    $sA = sertMatriksPaket();
    $eventA = sertMatriksSelesaikan($sA);
    ['sertifikat' => $sertA] = sertMatriksTerbit($sA, $eventA);
    $sB = sertMatriksPaket();
    $eventB = sertMatriksSelesaikan($sB);
    ['sertifikat' => $sertB] = sertMatriksTerbit($sB, $eventB);
    $stafB = sertMatriksStafReadOnly($sB);
    [$orgA, $slugA] = sertMatriksParam($sA);
    [$orgB, $slugB] = sertMatriksParam($sB);

    $this->actingAs($stafB)
        ->get(route('organizer.events.certificates.index', [$orgA, $slugA]))
        ->assertNotFound();
    $this->actingAs($stafB)
        ->get(route('organizer.events.certificates.show', [$orgA, $slugA, $sertA->id]))
        ->assertNotFound();
    $this->actingAs($stafB)->withSession(sertMatriksKonfirmasi())
        ->post(route('organizer.events.certificates.issue', [$orgA, $slugA]))
        ->assertNotFound();
    $this->actingAs($stafB)->withSession(sertMatriksKonfirmasi())
        ->post(route('organizer.events.certificates.revoke', [$orgA, $slugA, $sertA->id]), [
            'reason' => 'Alasan pencabutan yang cukup panjang.',
        ])
        ->assertNotFound();
    $this->actingAs($stafB)
        ->get(route('my.certificates.download', $sertA->id))
        ->assertNotFound();

    $this->actingAs($sB['owner'])
        ->get(route('organizer.events.certificates.show', [$orgB, $slugB, $sertA->id]))
        ->assertNotFound();
    $this->actingAs($sB['owner'])
        ->get(route('organizer.events.certificates.show', [$orgA, $slugA, $sertB->id]))
        ->assertNotFound();

    expect($sertA->refresh()->revoked_at)->toBeNull()
        ->and($sertB->refresh()->revoked_at)->toBeNull()
        ->and(Certificate::where('event_id', $eventA->id)->count())->toBe(1)
        ->and(Certificate::where('event_id', $eventB->id)->count())->toBe(1);
});

it('sertTesSertifikatMilikSendiri', function (): void {
    $s = sertMatriksPaket();
    $event = sertMatriksSelesaikan($s);
    $aku = User::factory()->create();
    $orang = User::factory()->create();
    $tugasku = sertMatriksTugas($s, sertMatriksDaftar($s, $aku), sertMatriksShift($s));
    sertMatriksHadir($tugasku);
    $tugasDia = sertMatriksTugas($s, sertMatriksDaftar($s, $orang), sertMatriksShift($s));
    sertMatriksHadir($tugasDia);
    $hasil = app(CertificateService::class)->issueBatch($event->refresh(), $s['owner']);
    $milikku = collect($hasil['issued'])->firstWhere('user_id', $aku->id)->refresh();
    $milikDia = collect($hasil['issued'])->firstWhere('user_id', $orang->id)->refresh();

    $respon = $this->actingAs($aku)->get(route('my.certificates.index'));

    $respon->assertOk()
        ->assertSee($milikku->certificate_no)
        ->assertDontSee($milikDia->certificate_no);

    $this->actingAs($aku)
        ->get(route('my.certificates.download', $milikku->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($aku)
        ->get(route('my.certificates.download', $milikDia->id))
        ->assertNotFound();
});

it('sertTesOwnerMengelolaSertifikat', function (): void {
    $s = sertMatriksPaket();
    $event = sertMatriksSelesaikan($s);
    $relawan = User::factory()->create();
    $tugas = sertMatriksTugas($s, sertMatriksDaftar($s, $relawan), sertMatriksShift($s));
    sertMatriksHadir($tugas);
    [$org, $slug] = sertMatriksParam($s);

    $this->actingAs($s['owner'])->withSession(sertMatriksKonfirmasi())
        ->post(route('organizer.events.certificates.issue', [$org, $slug]))
        ->assertRedirect(route('organizer.events.certificates.index', [$org, $slug]))
        ->assertSessionHas('status', '1 sertifikat diterbitkan, 0 dilewati.');

    $sertifikat = Certificate::where('event_id', $event->id)->firstOrFail();

    $this->actingAs($s['owner'])
        ->get(route('organizer.events.certificates.show', [$org, $slug, $sertifikat->id]))
        ->assertOk()
        ->assertSee($sertifikat->certificate_no);

    $alasan = 'Data kehadiran tidak valid setelah verifikasi ulang.';
    $this->actingAs($s['owner'])->withSession(sertMatriksKonfirmasi())
        ->post(route('organizer.events.certificates.revoke', [$org, $slug, $sertifikat->id]), [
            'reason' => $alasan,
        ])
        ->assertRedirect(route('organizer.events.certificates.show', [$org, $slug, $sertifikat->id]))
        ->assertSessionHas('status', "Sertifikat {$sertifikat->certificate_no} dicabut.");

    expect($sertifikat->refresh()->isRevoked())->toBeTrue()
        ->and($sertifikat->refresh()->revoke_reason)->toBe($alasan)
        ->and($tugas->refresh()->status)->toBe('completed');
});

it('sertTesAdminTanpaRuteCertificate', function (): void {
    expect(Route::has('admin.certificates.index'))->toBeFalse()
        ->and(Route::has('admin.certificates.issue'))->toBeFalse()
        ->and(Route::has('admin.certificates.show'))->toBeFalse()
        ->and(Route::has('admin.certificates.revoke'))->toBeFalse()
        ->and(Route::has('admin.registrations.index'))->toBeTrue();
});

it('sertTesBackfillMemberiPermOwnerLama', function (): void {
    $s = sertMatriksPaket();
    $s['owner']->revokePermissionTo(['certificate.issue', 'certificate.revoke', 'certificate.read']);
    expect($s['owner']->refresh()->can('certificate.issue'))->toBeFalse();

    (require base_path('database/migrations/2026_09_20_000003_backfill_certificate_permissions.php'))->up();

    expect($s['owner']->refresh()->can('certificate.issue'))->toBeTrue()
        ->and($s['owner']->can('certificate.revoke'))->toBeTrue()
        ->and($s['owner']->can('certificate.read'))->toBeTrue();
});

<?php

use App\Models\Certificate;
use App\Models\CertificateVerification;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function sertUnduhVerifikasiAcara(): Event
{
    $org = Organization::factory()->create(['status' => 'active']);
    $acara = Event::factory()->create(['organization_id' => $org->id]);

    return $acara->refresh();
}

function sertUnduhVerifikasiSertifikat(Event $acara, User $relawan): Certificate
{
    $pendaftaran = Registration::where('event_id', $acara->id)->where('user_id', $relawan->id)->first();
    if ($pendaftaran === null) {
        $divisi = EventDivision::factory()->create(['event_id' => $acara->id]);
        $peran = EventRole::factory()->create([
            'event_id' => $acara->id,
            'division_id' => $divisi->id,
        ]);
        $pendaftaran = Registration::factory()->create([
            'user_id' => $relawan->id,
            'event_id' => $acara->id,
            'role_id' => $peran->id,
            'status' => 'accepted',
        ]);
    }

    return Certificate::unguarded(fn (): Certificate => Certificate::create([
        'event_id' => $acara->id,
        'user_id' => $relawan->id,
        'registration_id' => $pendaftaran->id,
        'certificate_no' => 'WV-'.now()->format('Y').'-'.strtoupper(Str::random(6)),
        'issued_at' => now(),
        'qr_token_hash' => hash('sha256', Str::random(64)),
    ]))->refresh();
}

it('sertTesVolunteerLihatDaftarSendiri', function (): void {
    $acara = sertUnduhVerifikasiAcara();
    $acaraKedua = sertUnduhVerifikasiAcara();
    $aku = User::factory()->create();
    $orangLain = User::factory()->create();
    sertUnduhVerifikasiSertifikat($acara, $aku);
    sertUnduhVerifikasiSertifikat($acaraKedua, $aku);
    $sertifikatOrang = sertUnduhVerifikasiSertifikat($acara, $orangLain);

    $respon = $this->actingAs($aku)->get(route('my.certificates.index'));

    $respon->assertOk();
    expect($respon->viewData('certificates')->total())->toBe(2)
        ->and($respon->viewData('certificates')->pluck('id')->contains($sertifikatOrang->id))->toBeFalse();
});

it('sertTesUnduhPdfMilikSendiri', function (): void {
    $acara = sertUnduhVerifikasiAcara();
    $aku = User::factory()->create();
    $sertifikat = sertUnduhVerifikasiSertifikat($acara, $aku);

    $respon = $this->actingAs($aku)->get(route('my.certificates.download', $sertifikat->id));

    $respon->assertOk();
    expect($respon->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(str_starts_with($respon->getContent(), '%PDF'))->toBeTrue();
});

it('sertTesUnduhMilikOrang404', function (): void {
    $acara = sertUnduhVerifikasiAcara();
    $aku = User::factory()->create();
    $orangLain = User::factory()->create();
    $sertifikatOrang = sertUnduhVerifikasiSertifikat($acara, $orangLain);

    $this->actingAs($aku)
        ->get(route('my.certificates.download', $sertifikatOrang->id))
        ->assertNotFound();
});

it('sertTesUnduhDicabut422', function (): void {
    $acara = sertUnduhVerifikasiAcara();
    $aku = User::factory()->create();
    $sertifikat = sertUnduhVerifikasiSertifikat($acara, $aku);
    $sertifikat->forceFill([
        'revoked_at' => now(),
        'revoke_reason' => 'Data kehadiran tidak valid setelah verifikasi ulang.',
    ])->save();

    $this->actingAs($aku)
        ->get(route('my.certificates.download', $sertifikat->id))
        ->assertStatus(422)
        ->assertSee('Sertifikat ini telah dicabut.');
});

it('sertTesVerifikasiPublikValid', function (): void {
    $acara = sertUnduhVerifikasiAcara();
    $aku = User::factory()->create();
    $sertifikat = sertUnduhVerifikasiSertifikat($acara, $aku);

    $respon = $this->get(route('certificates.verify', $sertifikat->certificate_no));

    $respon->assertOk()
        ->assertSee($sertifikat->certificate_no)
        ->assertSee($aku->name)
        ->assertSee('VALID');
    expect(CertificateVerification::where('certificate_id', $sertifikat->id)->count())->toBe(1);
});

it('sertTesVerifikasiDicabut', function (): void {
    $acara = sertUnduhVerifikasiAcara();
    $aku = User::factory()->create();
    $sertifikat = sertUnduhVerifikasiSertifikat($acara, $aku);
    $sertifikat->forceFill([
        'revoked_at' => now(),
        'revoke_reason' => 'Data kehadiran tidak valid setelah verifikasi ulang.',
    ])->save();

    $this->get(route('certificates.verify', $sertifikat->certificate_no))
        ->assertOk()
        ->assertSee('DICABUT');
});

it('sertTesVerifikasiAsing404TanpaLog', function (): void {
    $sebelum = CertificateVerification::count();

    $this->get(route('certificates.verify', 'WV-2026-ASING1'))
        ->assertNotFound();

    expect(CertificateVerification::count())->toBe($sebelum);
});

it('sertTesVerifikasiNoindex', function (): void {
    $acara = sertUnduhVerifikasiAcara();
    $aku = User::factory()->create();
    $sertifikat = sertUnduhVerifikasiSertifikat($acara, $aku);

    $this->get(route('certificates.verify', $sertifikat->certificate_no))
        ->assertOk()
        ->assertSee('noindex', false);
});

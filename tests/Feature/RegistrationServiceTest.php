<?php

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @return array{org: Organization, owner: User, event: Event, role: EventRole, volunteer: User, field: EventCustomField} */
function regTesSetup(int $kuota = 5): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $event->forceFill(['status' => 'registration_open'])->save();
    $divisi = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $divisi->id,
        'quota' => $kuota,
        'accepted_count' => 0,
    ]);
    $volunteer = User::factory()->create();
    $field = EventCustomField::factory()->create(['event_id' => $event->id, 'type' => 'text']);

    return [
        'org' => $org,
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'role' => $role->refresh(),
        'volunteer' => $volunteer->refresh(),
        'field' => $field->refresh(),
    ];
}

/** @return array<string, mixed> */
function regTesJawaban(EventCustomField $field, string $nilai = 'Saya siap membantu.'): array
{
    return [[
        'event_custom_field_id' => $field->id,
        'value_text' => $nilai,
    ]];
}

function regTesSubmit(array $s, ?User $user = null, ?string $kunci = null): Registration
{
    return app(RegistrationService::class)->submit(
        $s['event'],
        $s['role'],
        $user ?? $s['volunteer'],
        regTesJawaban($s['field']),
        $kunci ?? (string) Str::uuid(),
    );
}

it('submit membuat registrasi pending beserta jawaban dan riwayat', function (): void {
    $s = regTesSetup();

    $reg = regTesSubmit($s);

    expect($reg->status)->toBe('pending')
        ->and($reg->user_id)->toBe($s['volunteer']->id)
        ->and($reg->event_id)->toBe($s['event']->id)
        ->and($reg->role_id)->toBe($s['role']->id)
        ->and($reg->answers()->count())->toBe(1)
        ->and($reg->histories()->count())->toBe(1)
        ->and($reg->histories()->first()->to_status)->toBe('pending')
        ->and($reg->histories()->first()->changed_by)->toBe($s['volunteer']->id);
});

it('submit duplikat aktif dari user yang sama ditolak 422', function (): void {
    $s = regTesSetup();
    regTesSubmit($s);

    try {
        regTesSubmit($s);
        $this->fail('Seharusnya menolak duplikat aktif.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }
});

it('withdraw oleh pemilik mengubah status menjadi withdrawn', function (): void {
    $s = regTesSetup();
    $reg = regTesSubmit($s);

    $hasil = app(RegistrationService::class)->withdraw($reg, $s['volunteer']);

    expect($hasil->status)->toBe('withdrawn');
});

it('withdraw oleh user lain ditolak 403', function (): void {
    $s = regTesSetup();
    $reg = regTesSubmit($s);
    $orangLain = User::factory()->create();

    try {
        app(RegistrationService::class)->withdraw($reg, $orangLain);
        $this->fail('Seharusnya menolak withdraw milik orang lain.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403)
            ->and($reg->refresh()->status)->toBe('pending');
    }
});

it('review under_review ke accepted menaikkan counter kuota', function (): void {
    $s = regTesSetup();
    $svc = app(RegistrationService::class);
    $reg = regTesSubmit($s);
    $svc->review($reg->refresh(), 'under_review', $s['owner']);

    $hasil = $svc->review($reg->refresh(), 'accepted', $s['owner']);

    expect($hasil->status)->toBe('accepted')
        ->and($s['role']->refresh()->accepted_count)->toBe(1);
});

it('transisi invalid pending ke accepted langsung ditolak 422', function (): void {
    $s = regTesSetup();
    $reg = regTesSubmit($s);

    try {
        app(RegistrationService::class)->review($reg, 'accepted', $s['owner']);
        $this->fail('Seharusnya menolak transisi invalid.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($reg->refresh()->status)->toBe('pending');
    }
});

it('submit dengan status accepted di payload tetap pending', function (): void {
    $s = regTesSetup();

    $reg = app(RegistrationService::class)->submit(
        $s['event'],
        $s['role'],
        $s['volunteer'],
        [...regTesJawaban($s['field']), 'status' => 'accepted'],
        (string) Str::uuid(),
    );

    expect($reg->status)->toBe('pending');
});

it('review rejected tanpa alasan ditolak 422', function (): void {
    $s = regTesSetup();
    $svc = app(RegistrationService::class);
    $reg = regTesSubmit($s);
    $svc->review($reg->refresh(), 'under_review', $s['owner']);

    try {
        $svc->review($reg->refresh(), 'rejected', $s['owner']);
        $this->fail('Seharusnya mewajibkan alasan penolakan.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }
});

it('cancel dari accepted melepas kuota', function (): void {
    $s = regTesSetup();
    $svc = app(RegistrationService::class);
    $reg = regTesSubmit($s);
    $svc->review($reg->refresh(), 'under_review', $s['owner']);
    $svc->review($reg->refresh(), 'accepted', $s['owner']);
    expect($s['role']->refresh()->accepted_count)->toBe(1);

    $hasil = $svc->cancel($reg->refresh(), $s['owner'], 'Acara dibatalkan panitia.');

    expect($hasil->status)->toBe('cancelled')
        ->and($s['role']->refresh()->accepted_count)->toBe(0);
});

it('bulkReview memproses semua id dalam satu transaksi', function (): void {
    $s = regTesSetup();
    $svc = app(RegistrationService::class);
    $ids = [];
    foreach (range(1, 3) as $i) {
        $relawan = User::factory()->create();
        $pendaftaran = $svc->submit($s['event'], $s['role'], $relawan, regTesJawaban($s['field']), (string) Str::uuid());
        $svc->review($pendaftaran->refresh(), 'under_review', $s['owner']);
        $ids[] = $pendaftaran->id;
    }

    $hasil = $svc->bulkReview($s['event'], $ids, 'accepted', $s['owner']);

    expect($hasil)->toHaveCount(3)
        ->and($s['role']->refresh()->accepted_count)->toBe(3);
});

it('bulkReview dengan id lintas event gagal fail-closed 404', function (): void {
    $s = regTesSetup();
    $svc = app(RegistrationService::class);
    $reg = regTesSubmit($s);
    $svc->review($reg->refresh(), 'under_review', $s['owner']);

    $lain = regTesSetup();
    $asing = regTesSubmit($lain);
    $svc->review($asing->refresh(), 'under_review', $lain['owner']);

    try {
        $svc->bulkReview($s['event'], [$reg->id, $asing->id], 'accepted', $s['owner']);
        $this->fail('Seharusnya fail-closed 404.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404)
            ->and($reg->refresh()->status)->toBe('under_review')
            ->and($s['role']->refresh()->accepted_count)->toBe(0);
    }
});

it('bulkReview lebih dari 50 id ditolak 422', function (): void {
    $s = regTesSetup();
    $ids = range(1, 51);

    try {
        app(RegistrationService::class)->bulkReview($s['event'], $ids, 'accepted', $s['owner']);
        $this->fail('Seharusnya menolak bulk lebih dari 50 ID.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }
});

it('submit idempotent dengan kunci sama mengembalikan record existing', function (): void {
    $s = regTesSetup();
    $kunci = (string) Str::uuid();
    $pertama = regTesSubmit($s, $s['volunteer'], $kunci);

    $kedua = regTesSubmit($s, $s['volunteer'], $kunci);

    expect($kedua->id)->toBe($pertama->id)
        ->and(Registration::where('idempotency_key', $kunci)->count())->toBe(1);
});

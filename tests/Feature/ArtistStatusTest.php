<?php

use App\Models\Artist;
use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\ArtistService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @return array{0: Event, 1: User} */
function buatPaketArtis(): array
{
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $event = Event::factory()->create(['organization_id' => $org->id]);

    return [$event, $owner];
}

/** @param array<string, mixed> $overrides @return array{0: Event, 1: User, 2: Artist} */
function buatArtis(array $overrides = []): array
{
    [$event, $owner] = buatPaketArtis();
    $artis = Artist::unguarded(fn (): Artist => Artist::create(array_merge([
        'event_id' => $event->id,
        'name' => 'Band Test',
        'status' => 'scheduled',
        'attendance' => 'expected',
    ], $overrides)));

    return [$event, $owner, $artis];
}

it('membuat artis dengan status awal scheduled', function () {
    [$event, $owner] = buatPaketArtis();

    $artis = app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Baru',
        'scheduled_at' => $event->start_at->copy()->addHour()->toDateTimeString(),
        'duration_minutes' => 90,
    ]);

    expect($artis->status)->toBe('scheduled')
        ->and($artis->attendance)->toBe('expected')
        ->and($artis->event_id)->toBe($event->id);
});

it('jadwal di luar rentang event ditolak 422', function () {
    [$event, $owner] = buatPaketArtis();

    app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Nyasar',
        'scheduled_at' => $event->start_at->copy()->subDay()->toDateTimeString(),
    ]);
})->throws(HttpException::class, 'Jadwal harus dalam rentang event.');

it('durasi di luar 15-240 menit ditolak 422', function () {
    [$event, $owner] = buatPaketArtis();

    app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Lama',
        'duration_minutes' => 10,
    ]);
})->throws(HttpException::class, 'Durasi 15–240 menit.');

it('rantai transisi penuh scheduled sampai done', function () {
    [$event, $owner, $artis] = buatArtis();
    $svc = app(ArtistService::class);

    foreach (['soundcheck', 'performing', 'done'] as $tahap) {
        $hasil = $svc->transition($artis->refresh(), $owner, $tahap);
        expect($hasil->status)->toBe($tahap);
    }

    expect($artis->refresh()->status)->toBe('done')
        ->and($artis->histories()->count())->toBe(3);
});

it('lompat langkah ditolak 422', function () {
    [$event, $owner, $artis] = buatArtis(['status' => 'scheduled']);

    app(ArtistService::class)->transition($artis, $owner, 'performing');
})->throws(HttpException::class, 'Tahap berikutnya');

it('mundur langkah ditolak 422', function () {
    [$event, $owner, $artis] = buatArtis(['status' => 'soundcheck']);

    app(ArtistService::class)->transition($artis, $owner, 'scheduled');
})->throws(HttpException::class, 'Tahap berikutnya');

it('cancel berhasil dan mencatat history', function () {
    [$event, $owner, $artis] = buatArtis(['status' => 'soundcheck']);

    $hasil = app(ArtistService::class)->cancel($artis, $owner, 'Panggung utama rusak parah.');

    expect($hasil->status)->toBe('cancelled');
    $riwayat = $artis->histories()->latest('id')->first();
    expect($riwayat->from_status)->toBe('soundcheck')
        ->and($riwayat->to_status)->toBe('cancelled');
});

it('cancel tanpa alasan ditolak 422', function () {
    [$event, $owner, $artis] = buatArtis();

    app(ArtistService::class)->cancel($artis, $owner, '   ');
})->throws(HttpException::class, 'Alasan pembatalan minimal 10 karakter.');

it('cancel dari done ditolak 422', function () {
    [$event, $owner, $artis] = buatArtis(['status' => 'done']);

    app(ArtistService::class)->cancel($artis, $owner, 'Alasan yang cukup panjang.');
})->throws(HttpException::class, 'Hanya artis yang belum tampil yang dapat dibatalkan.');

it('cancel dari cancelled ditolak 422', function () {
    [$event, $owner, $artis] = buatArtis(['status' => 'cancelled']);

    app(ArtistService::class)->cancel($artis, $owner, 'Alasan yang cukup panjang.');
})->throws(HttpException::class, 'Hanya artis yang belum tampil yang dapat dibatalkan.');

it('kehadiran bolak-balik arrived dan no_show', function () {
    [$event, $owner, $artis] = buatArtis();
    $svc = app(ArtistService::class);

    expect($svc->markAttendance($artis, $owner, 'arrived')->attendance)->toBe('arrived')
        ->and($svc->markAttendance($artis->refresh(), $owner, 'no_show')->attendance)->toBe('no_show')
        ->and($artis->histories()->count())->toBe(2);
});

it('kehadiran tak menyentuh status alur', function () {
    [$event, $owner, $artis] = buatArtis(['status' => 'performing']);

    $hasil = app(ArtistService::class)->markAttendance($artis, $owner, 'arrived');

    expect($hasil->attendance)->toBe('arrived')
        ->and($hasil->status)->toBe('performing');
});

it('kehadiran yang sama ditolak 422', function () {
    [$event, $owner, $artis] = buatArtis(['attendance' => 'expected']);

    app(ArtistService::class)->markAttendance($artis, $owner, 'expected');
})->throws(HttpException::class, 'Status kehadiran sudah expected.');

it('update mengabaikan status attendance event dan rider', function () {
    [$event, $owner, $artis] = buatArtis();

    $hasil = app(ArtistService::class)->update($artis, $owner, [
        'name' => 'Nama Baru',
        'status' => 'done',
        'attendance' => 'arrived',
        'event_id' => 999999,
        'rider_fulfilled' => true,
    ]);

    expect($hasil->name)->toBe('Nama Baru')
        ->and($hasil->status)->toBe('scheduled')
        ->and($hasil->attendance)->toBe('expected')
        ->and($hasil->event_id)->toBe($event->id)
        ->and($hasil->rider_fulfilled)->toBeFalse();
});

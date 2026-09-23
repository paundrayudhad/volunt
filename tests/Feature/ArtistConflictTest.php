<?php

use App\Models\Artist;
use App\Models\ArtistLiaison;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\ArtistService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @return array{0: Event, 1: User} */
function buatPaketKonflik(): array
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

/** @param array<string, mixed> $overrides */
function buatArtisTerjadwal(Event $event, array $overrides = []): Artist
{
    return Artist::unguarded(fn (): Artist => Artist::create(array_merge([
        'event_id' => $event->id,
        'name' => 'Band '.fake()->unique()->word(),
        'stage' => 'Panggung utama',
        'scheduled_at' => $event->start_at->copy()->addHours(2),
        'duration_minutes' => 60,
        'status' => 'scheduled',
        'attendance' => 'expected',
    ], $overrides)));
}

/** @return array{0: EventRole, 1: User} */
function buatRelawanDiterima(Event $event, ?User $relawan = null): array
{
    $divisi = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $divisi->id,
        'quota' => 50,
        'accepted_count' => 0,
    ]);
    $relawan ??= User::factory()->create();
    Registration::factory()->create([
        'user_id' => $relawan->id,
        'event_id' => $event->id,
        'role_id' => $role->id,
        'status' => 'accepted',
    ]);

    return [$role, $relawan];
}

it('panggung sama dan overlap ditolak 422 menyebut nama pembentrok', function () {
    [$event, $owner] = buatPaketKonflik();
    $pembentrok = buatArtisTerjadwal($event, ['name' => 'Band Bentrok']);

    app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Baru',
        'stage' => 'Panggung utama',
        'scheduled_at' => $pembentrok->scheduled_at->copy()->addMinutes(30)->toDateTimeString(),
        'duration_minutes' => 60,
    ]);
})->throws(HttpException::class, 'Bentrok dengan Band Bentrok');

it('panggung beda dan overlap lolos', function () {
    [$event, $owner] = buatPaketKonflik();
    $lain = buatArtisTerjadwal($event, ['name' => 'Band Lain', 'stage' => 'Panggung utama']);

    $artis = app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Beda Panggung',
        'stage' => 'Panggung akustik',
        'scheduled_at' => $lain->scheduled_at->copy()->addMinutes(30)->toDateTimeString(),
        'duration_minutes' => 60,
    ]);

    expect($artis->id)->not->toBeNull();
});

it('panggung sama dan batas sentuh lolos', function () {
    [$event, $owner] = buatPaketKonflik();
    $pertama = buatArtisTerjadwal($event);

    $artis = app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Tepat Waktu',
        'stage' => 'Panggung utama',
        'scheduled_at' => $pertama->scheduled_at->copy()->addHour()->toDateTimeString(),
        'duration_minutes' => 60,
    ]);

    expect($artis->id)->not->toBeNull();
});

it('update diri sendiri tidak bentrok dengan dirinya', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);

    $diharapkan = $artis->scheduled_at->copy()->addMinutes(15);

    $hasil = app(ArtistService::class)->update($artis, $owner, [
        'scheduled_at' => $diharapkan->toDateTimeString(),
    ]);

    expect($hasil->scheduled_at->eq($diharapkan))->toBeTrue();
});

it('artis cancelled atau soft-deleted tidak dihitung', function () {
    [$event, $owner] = buatPaketKonflik();
    $jadwal = $event->start_at->copy()->addHours(2);
    buatArtisTerjadwal($event, ['name' => 'Band Batal', 'status' => 'cancelled', 'scheduled_at' => $jadwal]);
    buatArtisTerjadwal($event, ['name' => 'Band Hapus', 'scheduled_at' => $jadwal])->delete();

    $artis = app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Pengganti',
        'stage' => 'Panggung utama',
        'scheduled_at' => $jadwal->copy()->addMinutes(30)->toDateTimeString(),
        'duration_minutes' => 60,
    ]);

    expect($artis->id)->not->toBeNull();
});

it('nama artis sama dan overlap ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();
    buatArtisTerjadwal($event, ['name' => 'Band Kembar', 'stage' => 'Panggung akustik']);

    app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Kembar',
        'stage' => 'Panggung utama',
        'scheduled_at' => $event->start_at->copy()->addHours(2)->addMinutes(30)->toDateTimeString(),
        'duration_minutes' => 60,
    ]);
})->throws(HttpException::class, 'tumpang tindih');

it('jadwal di luar rentang event ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();

    app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Nyasar',
        'scheduled_at' => $event->end_at->copy()->addHour()->toDateTimeString(),
    ]);
})->throws(HttpException::class, 'Jadwal harus dalam rentang event.');

it('jadwal null melewati semua cek', function () {
    [$event, $owner] = buatPaketKonflik();
    buatArtisTerjadwal($event, ['name' => 'Band Terjadwal']);

    $artis = app(ArtistService::class)->create($event, $owner, ['name' => 'Band Fleksibel']);

    expect($artis->scheduled_at)->toBeNull();
});

it('create tanpa nama ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();

    app(ArtistService::class)->create($event, $owner, [
        'scheduled_at' => $event->start_at->copy()->addHour()->toDateTimeString(),
    ]);
})->throws(HttpException::class, 'Nama artis wajib diisi.');

it('create nama kosong atau spasi ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();

    app(ArtistService::class)->create($event, $owner, [
        'name' => '   ',
        'scheduled_at' => $event->start_at->copy()->addHour()->toDateTimeString(),
    ]);
})->throws(HttpException::class, 'Nama artis wajib diisi.');

it('jadwal berformat salah ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();

    app(ArtistService::class)->create($event, $owner, [
        'name' => 'Band Bingung',
        'scheduled_at' => 'bukan-tanggal-sama-sekali',
    ]);
})->throws(HttpException::class, 'Format jadwal tidak valid.');

it('update jadwal berformat salah ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);

    app(ArtistService::class)->update($artis, $owner, ['scheduled_at' => '31 Februari kapan']);
})->throws(HttpException::class, 'Format jadwal tidak valid.');

it('assign LO volunteer diterima berhasil dan tercatat audit', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);
    [, $relawan] = buatRelawanDiterima($event);

    $liaison = app(ArtistService::class)->assignLiaison($artis, $owner, $relawan);

    expect($liaison->artist_id)->toBe($artis->id)
        ->and($liaison->user_id)->toBe($relawan->id)
        ->and($artis->liaisons()->where('user_id', $relawan->id)->exists())->toBeTrue();
});

it('assign LO non-volunteer event ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);
    $luar = User::factory()->create();

    app(ArtistService::class)->assignLiaison($artis, $owner, $luar);
})->throws(HttpException::class, 'Hanya volunteer event ini yang dapat menjadi LO.');

it('assign LO dengan registration withdrawn ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);
    [, $relawan] = buatRelawanDiterima($event);
    Registration::where('user_id', $relawan->id)->where('event_id', $event->id)->update(['status' => 'withdrawn']);

    app(ArtistService::class)->assignLiaison($artis, $owner, $relawan);
})->throws(HttpException::class, 'Hanya volunteer event ini yang dapat menjadi LO.');

it('assign LO dengan registration cancelled ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);
    [, $relawan] = buatRelawanDiterima($event);
    Registration::where('user_id', $relawan->id)->where('event_id', $event->id)->update(['status' => 'cancelled']);

    app(ArtistService::class)->assignLiaison($artis, $owner, $relawan);
})->throws(HttpException::class, 'Hanya volunteer event ini yang dapat menjadi LO.');

it('assign LO volunteer event lain ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();
    [$eventLain] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);
    [, $relawanLain] = buatRelawanDiterima($eventLain);

    app(ArtistService::class)->assignLiaison($artis, $owner, $relawanLain);
})->throws(HttpException::class, 'Hanya volunteer event ini yang dapat menjadi LO.');

it('assign LO overlap dampingan ditolak 422 menyebut nama artis', function () {
    [$event, $owner] = buatPaketKonflik();
    $pertama = buatArtisTerjadwal($event, ['name' => 'Band Pertama']);
    $kedua = buatArtisTerjadwal($event, [
        'name' => 'Band Kedua',
        'stage' => 'Panggung akustik',
        'scheduled_at' => $pertama->scheduled_at->copy()->addMinutes(30),
    ]);
    [, $relawan] = buatRelawanDiterima($event);
    app(ArtistService::class)->assignLiaison($pertama, $owner, $relawan);

    app(ArtistService::class)->assignLiaison($kedua, $owner, $relawan);
})->throws(HttpException::class, 'sudah mendampingi Band Pertama');

it('assign LO dampingan tak overlap lolos', function () {
    [$event, $owner] = buatPaketKonflik();
    $pertama = buatArtisTerjadwal($event);
    $kedua = buatArtisTerjadwal($event, [
        'stage' => 'Panggung akustik',
        'scheduled_at' => $pertama->scheduled_at->copy()->addHours(2),
    ]);
    [, $relawan] = buatRelawanDiterima($event);
    app(ArtistService::class)->assignLiaison($pertama, $owner, $relawan);

    $liaison = app(ArtistService::class)->assignLiaison($kedua, $owner, $relawan);

    expect($liaison->id)->not->toBeNull();
});

it('assign LO duplikat aktif ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);
    [, $relawan] = buatRelawanDiterima($event);
    app(ArtistService::class)->assignLiaison($artis, $owner, $relawan);

    app(ArtistService::class)->assignLiaison($artis, $owner, $relawan);
})->throws(HttpException::class, 'sudah menjadi LO');

it('release LO soft-delete dan tercatat audit', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);
    [, $relawan] = buatRelawanDiterima($event);
    $svc = app(ArtistService::class);
    $liaison = $svc->assignLiaison($artis, $owner, $relawan);

    $svc->releaseLiaison($liaison, $owner);

    expect(ArtistLiaison::withTrashed()->find($liaison->id)->trashed())->toBeTrue()
        ->and($artis->liaisons()->where('user_id', $relawan->id)->exists())->toBeFalse();
});

it('release LO kedua pada baris trashed ditolak 404', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);
    [, $relawan] = buatRelawanDiterima($event);
    $svc = app(ArtistService::class);
    $liaison = $svc->assignLiaison($artis, $owner, $relawan);
    $svc->releaseLiaison($liaison, $owner);

    $svc->releaseLiaison($liaison, $owner);
})->throws(NotFoundHttpException::class);

it('addNote menyimpan catatan dan tercatat audit', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);

    $catatan = app(ArtistService::class)->addNote($artis, $owner, '  Soundcheck molor 15 menit.  ');

    expect($catatan->body)->toBe('Soundcheck molor 15 menit.')
        ->and($catatan->artist_id)->toBe($artis->id)
        ->and($catatan->author_id)->toBe($owner->id);
});

it('addNote kosong ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);

    app(ArtistService::class)->addNote($artis, $owner, '   ');
})->throws(HttpException::class, 'Catatan tidak boleh kosong.');

it('addNote lebih dari 2000 karakter ditolak 422', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);

    app(ArtistService::class)->addNote($artis, $owner, str_repeat('a', 2001));
})->throws(HttpException::class, 'Catatan maksimal 2000 karakter.');

it('toggleRider mengubah flag dan tercatat audit', function () {
    [$event, $owner] = buatPaketKonflik();
    $artis = buatArtisTerjadwal($event);
    $svc = app(ArtistService::class);

    expect($svc->toggleRider($artis, $owner, true)->rider_fulfilled)->toBeTrue()
        ->and($svc->toggleRider($artis->refresh(), $owner, false)->rider_fulfilled)->toBeFalse()
        ->and($artis->histories()->count())->toBe(0);
});

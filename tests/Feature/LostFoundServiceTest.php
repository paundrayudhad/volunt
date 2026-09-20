<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\LostFoundService;
use App\Services\StoredPhoto;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/** @return array{org: Organization, pelapor: User, handler: User, event: Event} */
function buatPaketTemuan(): array
{
    $org = Organization::factory()->create();
    $pelapor = User::factory()->create();
    $handler = User::factory()->create();
    foreach ([$pelapor, $handler] as $user) {
        OrganizationMember::unguarded(fn () => OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'staff',
            'status' => 'active',
            'joined_at' => now(),
        ]));
    }
    $pelapor->givePermissionTo(['incident.report']);
    $handler->givePermissionTo(['lostfound.manage']);
    $event = Event::factory()->create(['organization_id' => $org->id]);

    return ['org' => $org, 'pelapor' => $pelapor, 'handler' => $handler, 'event' => $event];
}

function buatFotoPng(string $nama = 'barang.png'): UploadedFile
{
    // PNG 1x1 piksel tanpa perlu ekstensi GD di test runner.
    $biner = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $tmp = tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($tmp, $biner);

    return new UploadedFile($tmp, $nama, 'image/png', null, true);
}

it('klaim lalu setuju menjadi returned', function () {
    $paket = buatPaketTemuan();
    $svc = app(LostFoundService::class);
    $item = $svc->report($paket['event'], $paket['pelapor'], ['kind' => 'found', 'item_name' => 'Kunci motor', 'location' => 'Posko informasi']);
    $pengklaim = User::factory()->create();

    $svc->claim($item, $pengklaim);

    expect($item->refresh()->status)->toBe('claimed');
    $svc->resolveClaim($item->refresh(), $paket['handler'], 'returned', 'Cocok dengan ciri.');

    expect($item->refresh()->status)->toBe('returned');
});

it('tolak klaim kembali found dan claimant dibersihkan', function () {
    $paket = buatPaketTemuan();
    $svc = app(LostFoundService::class);
    $item = $svc->report($paket['event'], $paket['pelapor'], ['kind' => 'found', 'item_name' => 'Tas selempang', 'location' => 'Posko informasi']);
    $pengklaim = User::factory()->create();
    $svc->claim($item, $pengklaim);

    $svc->resolveClaim($item->refresh(), $paket['handler'], 'rejected', 'Ciri tidak cocok.');

    expect($item->refresh()->status)->toBe('found')
        ->and($item->refresh()->claimant_id)->toBeNull();
});

it('klaim kedua setelah claimed ditolak 404', function () {
    $paket = buatPaketTemuan();
    $svc = app(LostFoundService::class);
    $item = $svc->report($paket['event'], $paket['pelapor'], ['kind' => 'found', 'item_name' => 'Topi', 'location' => 'Pintu A']);
    $svc->claim($item, User::factory()->create());

    $svc->claim($item->refresh(), User::factory()->create());
})->throws(HttpException::class, 'Barang tidak tersedia untuk diklaim.');

it('klaim milik sendiri ditolak', function () {
    $paket = buatPaketTemuan();
    $svc = app(LostFoundService::class);
    $item = $svc->report($paket['event'], $paket['pelapor'], ['kind' => 'found', 'item_name' => 'Syal', 'location' => 'Tribun']);

    $svc->claim($item, $paket['pelapor']);
})->throws(HttpException::class, 'Tidak dapat mengklaim laporan sendiri.');

it('lapor lost berstatus open dan lapor found berstatus found', function () {
    $paket = buatPaketTemuan();
    $svc = app(LostFoundService::class);

    $hilang = $svc->report($paket['event'], $paket['pelapor'], ['kind' => 'lost', 'item_name' => 'Dompet', 'location' => 'Area konser']);
    $temu = $svc->report($paket['event'], $paket['pelapor'], ['kind' => 'found', 'item_name' => 'Dompet', 'location' => 'Posko informasi']);

    expect($hilang->status)->toBe('open')
        ->and($temu->status)->toBe('found');
});

it('tutup item dari returned menjadi closed', function () {
    $paket = buatPaketTemuan();
    $svc = app(LostFoundService::class);
    $item = $svc->report($paket['event'], $paket['pelapor'], ['kind' => 'found', 'item_name' => 'Jam tangan', 'location' => 'Posko informasi']);
    $svc->claim($item, User::factory()->create());
    $svc->resolveClaim($item->refresh(), $paket['handler'], 'returned', 'Cocok dengan ciri.');

    $svc->close($item->refresh(), $paket['handler']);

    expect($item->refresh()->status)->toBe('closed')
        ->and($item->refresh()->deleted_at)->toBeNull();
});

it('tutup item claimed ditolak 422', function () {
    $paket = buatPaketTemuan();
    $svc = app(LostFoundService::class);
    $item = $svc->report($paket['event'], $paket['pelapor'], ['kind' => 'found', 'item_name' => 'Kacamata', 'location' => 'Posko informasi']);
    $svc->claim($item, User::factory()->create());

    $svc->close($item->refresh(), $paket['handler']);
})->throws(HttpException::class, 'Hanya item found/returned yang dapat ditutup.');

it('lapor dengan foto tersimpan di disk fresh', function () {
    Storage::fake('local');
    $paket = buatPaketTemuan();
    $svc = app(LostFoundService::class);

    $item = $svc->report($paket['event'], $paket['pelapor'], ['kind' => 'found', 'item_name' => 'Kunci motor', 'location' => 'Posko informasi', 'photo' => buatFotoPng()]);

    expect($item->photo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($item->photo_path);
});

it('foto yang dihapus tidak tertinggal di disk', function () {
    Storage::fake('local');
    $path = StoredPhoto::fromUpload(buatFotoPng(), 'lost-found');
    Storage::disk('local')->assertExists($path);

    StoredPhoto::delete($path);

    Storage::disk('local')->assertMissing($path);
});

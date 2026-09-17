<?php

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function publikAcara(array $overrides = []): Event
{
    $event = Event::factory()->create($overrides);
    $event->forceFill([
        'status' => 'published',
        'published_at' => now(),
    ])->save();

    return $event->fresh();
}

it('tamu bisa melihat katalog event publik dengan paginasi dua belas', function (): void {
    Event::factory()->count(15)->create()->each(fn (Event $event) => $event->forceFill([
        'status' => 'published',
        'published_at' => now(),
    ])->save());
    Event::factory()->create(['name' => 'Rapat Internal Draft', 'status' => 'draft']);

    $res = $this->get(route('events.index'));

    $res->assertOk();
    $res->assertSee('Katalog Event');
    $res->assertDontSee('Rapat Internal Draft');
    expect($res->viewData('acara')->count())->toBe(12)
        ->and($res->viewData('acara')->total())->toBe(15);
});

it('pencarian katalog memfilter berdasarkan nama', function (): void {
    publikAcara(['name' => 'Festival Musik Merdeka']);
    publikAcara(['name' => 'Bakti Sosial Pesisir']);

    $res = $this->get(route('events.index', ['search' => 'Musik']));

    $res->assertOk();
    $res->assertSee('Festival Musik Merdeka');
    $res->assertDontSee('Bakti Sosial Pesisir');
});

it('filter kategori dan kota mempersempit katalog', function (): void {
    publikAcara(['name' => 'Konser Amal', 'category' => 'musik', 'venue' => 'Balai Kota Bandung']);
    publikAcara(['name' => 'Donor Darah', 'category' => 'kesehatan', 'venue' => 'Balai Kota Surabaya']);
    publikAcara(['name' => 'Lomba Lari', 'category' => 'olahraga', 'address' => 'Jl. Merdeka Bandung']);

    $res = $this->get(route('events.index', ['kategori' => 'musik']));

    $res->assertOk();
    $res->assertSee('Konser Amal');
    $res->assertDontSee('Donor Darah');

    $kota = $this->get(route('events.index', ['kota' => 'Bandung']));

    $kota->assertOk();
    $kota->assertSee('Konser Amal');
    $kota->assertSee('Lomba Lari');
    $kota->assertDontSee('Donor Darah');
});

it('halaman kedua katalog menampilkan sisa event', function (): void {
    Event::factory()->count(15)->create()->each(fn (Event $event) => $event->forceFill([
        'status' => 'published',
        'published_at' => now(),
    ])->save());

    $res = $this->get(route('events.index', ['page' => 2]));

    $res->assertOk();
    expect($res->viewData('acara')->count())->toBe(3);
});

it('event non-publik menghasilkan 404 tanpa bocor', function (): void {
    $draf = Event::factory()->create(['status' => 'draft']);
    $batal = Event::factory()->create();
    $batal->forceFill(['status' => 'cancelled'])->save();

    $this->get(route('events.show', $draf->slug))->assertNotFound();
    $this->get(route('events.show', $batal->slug))->assertNotFound();
    $this->get('/events/slug-tidak-ada')->assertNotFound();
});

it('detail publik menampilkan sisa kuota tanpa data internal', function (): void {
    $org = Organization::factory()->create(['status' => 'active']);
    $anggota = User::factory()->create(['email' => 'anggota-rahasia@example.com']);
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $anggota->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $event = publikAcara(['organization_id' => $org->id, 'name' => 'Festival Terbuka']);
    $divisi = EventDivision::factory()->for($event)->create(['name' => 'Panggung']);
    $peran = EventRole::factory()->for($event)->for($divisi, 'division')->create([
        'name' => 'Usher',
        'quota' => 5,
        'accepted_count' => 0,
    ]);
    EventShift::factory()->for($event)->for($divisi, 'division')->for($peran, 'role')->create([
        'location' => 'Pintu Utama',
    ]);

    $res = $this->get(route('events.show', $event->slug));

    $res->assertOk();
    $res->assertSee('Festival Terbuka');
    $res->assertSee('Usher');
    $res->assertSee('Sisa 5');
    $res->assertSee('Pintu Utama');
    $res->assertDontSee('anggota-rahasia@example.com');
    $res->assertDontSee('Kelola');
    $res->assertDontSee('Hapus event');
});

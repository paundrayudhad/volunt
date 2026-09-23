<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\ArtistService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event} */
function aptPaket(): array
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

    return ['org' => $org->refresh(), 'owner' => $owner->refresh(), 'event' => $event->refresh()];
}

/** @return array{org: Organization, owner: User, event: Event, staf: User} */
function aptStafReadOnly(array $paket): array
{
    $staf = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $paket['org']->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staf->refresh(), $paket['org']);
    $staf->refresh()->givePermissionTo('artist.read');

    return [...$paket, 'staf' => $staf->refresh()];
}

it('render halaman artis organizer dengan konten nyata', function () {
    $paket = aptPaket();
    $artis = app(ArtistService::class)->create($paket['event'], $paket['owner'], [
        'name' => 'Band Render',
        'stage' => 'Panggung Utama',
        'rider_text' => 'Air mineral dan handuk.',
    ]);

    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.artists.index', [$paket['org']->slug, $paket['event']->slug]))
        ->assertOk()
        ->assertSee('Band Render', false)
        ->assertSee('Tambah artis baru', false);

    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.artists.show', [$paket['org']->slug, $paket['event']->slug, $artis->id]))
        ->assertOk()
        ->assertSee('Band Render', false)
        ->assertSee('Panggung Utama', false)
        ->assertSee('Ubah tahap tampil', false)
        ->assertSee('wajib bila membatalkan', false)
        ->assertSee('Tugaskan LO', false);

    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.show', [$paket['org']->slug, $paket['event']->slug]))
        ->assertOk()
        ->assertSee('Kelola Artis', false)
        ->assertSee('Band Render', false)
        ->assertSee('Artis (1)', false);
});

it('render halaman dampingan LO dan sembunyikan form mutasi dari read-only', function () {
    $paket = aptPaket();
    $paket = aptStafReadOnly($paket);
    $artis = app(ArtistService::class)->create($paket['event'], $paket['owner'], ['name' => 'Band Dampingan']);
    $lo = User::factory()->create();
    $peran = EventRole::factory()->create(['event_id' => $paket['event']->id]);
    Registration::factory()->create([
        'user_id' => $lo->id,
        'event_id' => $paket['event']->id,
        'role_id' => $peran->id,
        'status' => 'accepted',
    ]);
    $lo->givePermissionTo('artist.liaise');
    app(ArtistService::class)->assignLiaison($artis, $paket['owner'], $lo->refresh());

    $this->actingAs($lo)
        ->get(route('my.liaison.index'))
        ->assertOk()
        ->assertSee('Band Dampingan', false);

    $this->actingAs($lo)
        ->get(route('my.liaison.show', [$artis->id]))
        ->assertOk()
        ->assertSee('Band Dampingan', false)
        ->assertSee('Perbarui status dampingan', false)
        ->assertSee('wajib bila membatalkan', false);

    $this->actingAs($paket['staf'])
        ->get(route('organizer.events.artists.index', [$paket['org']->slug, $paket['event']->slug]))
        ->assertOk()
        ->assertSee('Band Dampingan', false)
        ->assertDontSee('Tambah artis baru', false);

    $this->actingAs($paket['staf'])
        ->get(route('organizer.events.artists.show', [$paket['org']->slug, $paket['event']->slug, $artis->id]))
        ->assertOk()
        ->assertSee('Band Dampingan', false)
        ->assertDontSee('Ubah tahap tampil', false)
        ->assertDontSee('Tugaskan LO', false);
});

it('index artis diurutkan jadwal lalu urutan tampil, tanpa jadwal ke bawah', function () {
    $paket = aptPaket();
    $jadwalPagi = $paket['event']->start_at->copy()->addHours(2);
    $jadwalSiang = $paket['event']->start_at->copy()->addHours(5);

    $dini = app(ArtistService::class)->create($paket['event'], $paket['owner'], [
        'name' => 'Band AAA Dini',
        'scheduled_at' => $jadwalSiang->toDateTimeString(),
        'performance_order' => 9,
    ]);
    $pagi = app(ArtistService::class)->create($paket['event'], $paket['owner'], [
        'name' => 'Band BBB Pagi',
        'scheduled_at' => $jadwalPagi->toDateTimeString(),
        'performance_order' => 2,
    ]);
    $siang = app(ArtistService::class)->create($paket['event'], $paket['owner'], [
        'name' => 'Band CCC Siang',
        'scheduled_at' => $jadwalSiang->toDateTimeString(),
        'performance_order' => 1,
    ]);
    app(ArtistService::class)->create($paket['event'], $paket['owner'], ['name' => 'Band DDD Tanpa Jadwal']);

    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.artists.index', [$paket['org']->slug, $paket['event']->slug]))
        ->assertOk()
        ->assertSeeInOrder(['Band BBB Pagi', 'Band CCC Siang', 'Band AAA Dini', 'Band DDD Tanpa Jadwal'], false);

    expect($dini->id)->not->toBeNull()
        ->and($pagi->id)->not->toBeNull()
        ->and($siang->id)->not->toBeNull();
});

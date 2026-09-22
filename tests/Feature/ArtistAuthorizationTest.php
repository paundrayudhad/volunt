<?php

use App\Models\Artist;
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

/** @return array{org: Organization, owner: User, event: Event, staf: User} */
function aaPaket(): array
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

    $staf = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staf->refresh(), $org);

    return ['org' => $org->refresh(), 'owner' => $owner->refresh(), 'event' => $event->refresh(), 'staf' => $staf->refresh()];
}

/** @return array{org: Organization, owner: User, event: Event, staf: User, baca: User} */
function aaPaketBaca(array $paket): array
{
    $baca = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $paket['org']->id,
        'user_id' => $baca->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($baca->refresh(), $paket['org']);
    $baca->refresh()->givePermissionTo('artist.read');

    return [...$paket, 'baca' => $baca->refresh()];
}

function aaArtis(Event $event, User $owner, string $nama): Artist
{
    return app(ArtistService::class)->create($event, $owner, ['name' => $nama]);
}

function aaDiterima(Event $event, ?User $relawan = null): User
{
    $relawan ??= User::factory()->create();
    $peran = EventRole::factory()->create(['event_id' => $event->id]);
    Registration::factory()->create([
        'user_id' => $relawan->id,
        'event_id' => $event->id,
        'role_id' => $peran->id,
        'status' => 'accepted',
    ]);

    return $relawan->refresh();
}

/** Staf org yang juga volunteer accepted, tetapi bukan LO siapa pun. */
function aaStafRelawan(array $paket): User
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

    return aaDiterima($paket['event'], $staf->refresh());
}

/** Staf org + volunteer accepted + LO artis yang diberikan. */
function aaLo(array $paket, Artist $artis): User
{
    $lo = aaStafRelawan($paket);
    $lo->givePermissionTo('artist.liaise');
    app(ArtistService::class)->assignLiaison($artis, $paket['owner'], $lo->refresh());

    return $lo->refresh();
}

it('tamu diarahkan ke login', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Tamu');
    $lo = aaDiterima($paket['event']);
    $lo->givePermissionTo('artist.liaise');
    $liaison = app(ArtistService::class)->assignLiaison($artis, $paket['owner'], $lo);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->get(route('organizer.events.artists.index', [$org, $event]))->assertRedirect(route('login'));
    $this->get(route('organizer.events.artists.show', [$org, $event, $artis->id]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.artists.store', [$org, $event]), ['name' => 'Band Tamu Baru'])->assertRedirect(route('login'));
    $this->put(route('organizer.events.artists.update', [$org, $event, $artis->id]), ['name' => 'Band Tamu Ubah'])->assertRedirect(route('login'));
    $this->post(route('organizer.events.artists.transition', [$org, $event, $artis->id]), ['to' => 'soundcheck'])->assertRedirect(route('login'));
    $this->post(route('organizer.events.artists.assign', [$org, $event, $artis->id]), ['user_id' => $lo->id])->assertRedirect(route('login'));
    $this->post(route('organizer.events.artists.liaisons.release', [$org, $event, $liaison->id]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.artists.notes.store', [$org, $event, $artis->id]), ['body' => 'Catatan tamu.'])->assertRedirect(route('login'));
    $this->post(route('organizer.events.artists.rider.toggle', [$org, $event, $artis->id]), ['fulfilled' => true])->assertRedirect(route('login'));
    $this->delete(route('organizer.events.artists.destroy', [$org, $event, $artis->id]))->assertRedirect(route('login'));

    $this->get(route('my.liaison.index'))->assertRedirect(route('login'));
    $this->get(route('my.liaison.show', [$artis->id]))->assertRedirect(route('login'));
    $this->post(route('my.liaison.status', [$artis->id]), ['attendance' => 'arrived'])->assertRedirect(route('login'));
    $this->post(route('my.liaison.note', [$artis->id]), ['body' => 'Catatan tamu.'])->assertRedirect(route('login'));
    $this->post(route('my.liaison.rider', [$artis->id]), ['fulfilled' => true])->assertRedirect(route('login'));

    expect($artis->refresh()->status)->toBe('scheduled')
        ->and($artis->refresh()->name)->toBe('Band Tamu');
});

it('staf tanpa izin 403 index dan semua mutasi', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Staf');
    $calon = aaDiterima($paket['event']);
    $liaison = app(ArtistService::class)->assignLiaison($artis, $paket['owner'], aaDiterima($paket['event']));
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($paket['staf'])
        ->get(route('organizer.events.artists.index', [$org, $event]))
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->get(route('organizer.events.artists.show', [$org, $event, $artis->id]))
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.artists.store', [$org, $event]), ['name' => 'Band Staf Baru'])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->put(route('organizer.events.artists.update', [$org, $event, $artis->id]), ['name' => 'Band Staf Ubah'])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.artists.transition', [$org, $event, $artis->id]), ['to' => 'soundcheck'])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.artists.assign', [$org, $event, $artis->id]), ['user_id' => $calon->id])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.artists.liaisons.release', [$org, $event, $liaison->id]))
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.artists.notes.store', [$org, $event, $artis->id]), ['body' => 'Catatan staf.'])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.artists.rider.toggle', [$org, $event, $artis->id]), ['fulfilled' => true])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->delete(route('organizer.events.artists.destroy', [$org, $event, $artis->id]))
        ->assertForbidden();

    expect($artis->refresh()->status)->toBe('scheduled')
        ->and($artis->refresh()->name)->toBe('Band Staf')
        ->and($artis->refresh()->rider_fulfilled)->toBeFalse()
        ->and($artis->liaisons()->count())->toBe(1)
        ->and($artis->notes()->count())->toBe(0)
        ->and(Artist::where('event_id', $paket['event']->id)->count())->toBe(1)
        ->and($artis->refresh()->trashed())->toBeFalse();
});

it('staf read-only bisa baca tetapi 403 semua mutasi', function (): void {
    $paket = aaPaketBaca(aaPaket());
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Baca');
    $calon = aaDiterima($paket['event']);
    $liaison = app(ArtistService::class)->assignLiaison($artis, $paket['owner'], aaDiterima($paket['event']));
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($paket['baca'])
        ->get(route('organizer.events.artists.index', [$org, $event]))
        ->assertOk()
        ->assertSee('Band Baca', false);
    $this->actingAs($paket['baca'])
        ->get(route('organizer.events.artists.show', [$org, $event, $artis->id]))
        ->assertOk()
        ->assertSee('Band Baca', false);

    // Saran review Task 5: staf read-only yang bukan LO → 404 di scope LO.
    $this->actingAs($paket['baca'])
        ->get(route('my.liaison.show', [$artis->id]))
        ->assertNotFound();

    $this->actingAs($paket['baca'])
        ->post(route('organizer.events.artists.store', [$org, $event]), ['name' => 'Band Baca Baru'])
        ->assertForbidden();
    $this->actingAs($paket['baca'])
        ->put(route('organizer.events.artists.update', [$org, $event, $artis->id]), ['name' => 'Band Baca Ubah'])
        ->assertForbidden();
    $this->actingAs($paket['baca'])
        ->post(route('organizer.events.artists.transition', [$org, $event, $artis->id]), ['to' => 'soundcheck'])
        ->assertForbidden();
    $this->actingAs($paket['baca'])
        ->post(route('organizer.events.artists.assign', [$org, $event, $artis->id]), ['user_id' => $calon->id])
        ->assertForbidden();
    $this->actingAs($paket['baca'])
        ->post(route('organizer.events.artists.liaisons.release', [$org, $event, $liaison->id]))
        ->assertForbidden();
    $this->actingAs($paket['baca'])
        ->post(route('organizer.events.artists.notes.store', [$org, $event, $artis->id]), ['body' => 'Catatan baca.'])
        ->assertForbidden();
    $this->actingAs($paket['baca'])
        ->post(route('organizer.events.artists.rider.toggle', [$org, $event, $artis->id]), ['fulfilled' => true])
        ->assertForbidden();
    $this->actingAs($paket['baca'])
        ->delete(route('organizer.events.artists.destroy', [$org, $event, $artis->id]))
        ->assertForbidden();

    expect($artis->refresh()->status)->toBe('scheduled')
        ->and($artis->refresh()->name)->toBe('Band Baca');
});

it('staf-volunteer non-LO 403 di organizer dan 404 di scope LO', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Relawan');
    $bukanLo = aaStafRelawan($paket);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($bukanLo)
        ->get(route('organizer.events.artists.index', [$org, $event]))
        ->assertForbidden();

    $this->actingAs($bukanLo)
        ->get(route('my.liaison.index'))
        ->assertOk()
        ->assertDontSee('Band Relawan', false);
    $this->actingAs($bukanLo)
        ->get(route('my.liaison.show', [$artis->id]))
        ->assertNotFound();
    $this->actingAs($bukanLo)
        ->post(route('my.liaison.status', [$artis->id]), ['attendance' => 'arrived'])
        ->assertNotFound();

    // Volunteer murni (bukan member org) bahkan tak lolos binding organisasi.
    $murni = aaDiterima($paket['event']);
    $this->actingAs($murni)
        ->get(route('organizer.events.artists.index', [$org, $event]))
        ->assertNotFound();
    $this->actingAs($murni)
        ->get(route('my.liaison.show', [$artis->id]))
        ->assertNotFound();
});

it('LO mengelola status catatan dan rider artisnya', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Kelola');
    $lo = aaLo($paket, $artis);

    $this->actingAs($lo)
        ->post(route('my.liaison.status', [$artis->id]), ['to' => 'soundcheck'])
        ->assertRedirect(route('my.liaison.show', [$artis->id]));
    $this->actingAs($lo)
        ->post(route('my.liaison.note', [$artis->id]), ['body' => 'Soundcheck jam tujuh malam.'])
        ->assertRedirect(route('my.liaison.show', [$artis->id]));
    $this->actingAs($lo)
        ->post(route('my.liaison.rider', [$artis->id]), ['fulfilled' => true])
        ->assertRedirect(route('my.liaison.show', [$artis->id]));

    expect($artis->refresh()->status)->toBe('soundcheck')
        ->and($artis->refresh()->rider_fulfilled)->toBeTrue()
        ->and($artis->notes()->count())->toBe(1)
        ->and($artis->notes()->first()->body)->toBe('Soundcheck jam tujuh malam.')
        ->and($artis->histories()->whereNotNull('to_status')->count())->toBe(1);
});

it('LO mendapat 404 pada artis orang lain', function (): void {
    $paket = aaPaket();
    $milikku = aaArtis($paket['event'], $paket['owner'], 'Band Milikku');
    $orangLain = aaArtis($paket['event'], $paket['owner'], 'Band Orang Lain');
    $lo = aaLo($paket, $milikku);

    $this->actingAs($lo)
        ->get(route('my.liaison.show', [$orangLain->id]))
        ->assertNotFound();
    $this->actingAs($lo)
        ->post(route('my.liaison.status', [$orangLain->id]), ['attendance' => 'arrived'])
        ->assertNotFound();
    $this->actingAs($lo)
        ->post(route('my.liaison.note', [$orangLain->id]), ['body' => 'Catatan nyasar.'])
        ->assertNotFound();
    $this->actingAs($lo)
        ->post(route('my.liaison.rider', [$orangLain->id]), ['fulfilled' => true])
        ->assertNotFound();

    expect($orangLain->refresh()->status)->toBe('scheduled')
        ->and($orangLain->refresh()->attendance)->toBe('expected')
        ->and($orangLain->refresh()->rider_fulfilled)->toBeFalse()
        ->and($orangLain->notes()->count())->toBe(0)
        ->and($orangLain->histories()->count())->toBe(0);
});

it('LO 403 saat mengubah rider-teks via PUT organizer', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Rider');
    $lo = aaLo($paket, $artis);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($lo)
        ->put(route('organizer.events.artists.update', [$org, $event, $artis->id]), ['rider_text' => 'Ubah seenaknya.'])
        ->assertForbidden();

    expect($artis->refresh()->rider_text)->toBeNull();
});

it('lintas org 404 kedua arah', function (): void {
    $paketA = aaPaket();
    $paketB = aaPaket();
    $artisA = aaArtis($paketA['event'], $paketA['owner'], 'Band Org A');
    $artisB = aaArtis($paketB['event'], $paketB['owner'], 'Band Org B');
    $calonA = aaDiterima($paketA['event']);
    $orgA = $paketA['org']->slug;
    $eventA = $paketA['event']->slug;
    $orgB = $paketB['org']->slug;
    $eventB = $paketB['event']->slug;

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.artists.index', [$orgB, $eventB]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.artists.show', [$orgB, $eventB, $artisB->id]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.artists.transition', [$orgB, $eventB, $artisB->id]), ['to' => 'soundcheck'])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.artists.assign', [$orgB, $eventB, $artisB->id]), ['user_id' => $calonA->id])
        ->assertNotFound();

    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.artists.index', [$orgA, $eventA]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.artists.show', [$orgA, $eventA, $artisA->id]))
        ->assertNotFound();

    expect($artisA->refresh()->status)->toBe('scheduled')
        ->and($artisB->refresh()->status)->toBe('scheduled')
        ->and($artisA->liaisons()->count())->toBe(0)
        ->and($artisB->liaisons()->count())->toBe(0);
});

it('lintas event satu org 404', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Event');
    $eventLain = Event::factory()->create(['organization_id' => $paket['org']->id]);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.artists.show', [$org, $eventLain->slug, $artis->id]))
        ->assertNotFound();
    $this->actingAs($paket['owner'])
        ->post(route('organizer.events.artists.transition', [$org, $eventLain->slug, $artis->id]), ['to' => 'soundcheck'])
        ->assertNotFound();
    $this->actingAs($paket['owner'])
        ->post(route('organizer.events.artists.assign', [$org, $eventLain->slug, $artis->id]), ['user_id' => aaDiterima($paket['event'])->id])
        ->assertNotFound();

    // Sanity: jalur semestinya tetap 200 untuk owner.
    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.artists.show', [$org, $event, $artis->id]))
        ->assertOk();

    expect($artis->refresh()->status)->toBe('scheduled');
});

it('assign LO via HTTP: owner OK, staf 403, volunteer 404', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Assign');
    $calon = aaDiterima($paket['event']);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.artists.assign', [$org, $event, $artis->id]), ['user_id' => $calon->id])
        ->assertForbidden();

    $luar = aaDiterima($paket['event']);
    $this->actingAs($luar)
        ->post(route('organizer.events.artists.assign', [$org, $event, $artis->id]), ['user_id' => $luar->id])
        ->assertNotFound();

    $this->actingAs($paket['owner'])
        ->post(route('organizer.events.artists.assign', [$org, $event, $artis->id]), ['user_id' => $calon->id])
        ->assertRedirect(route('organizer.events.artists.show', [$org, $event, $artis->id]));

    $this->assertDatabaseHas('artist_liaisons', ['artist_id' => $artis->id, 'user_id' => $calon->id]);
});

it('release LO via HTTP membuat LO lama 404 di scope-nya', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Release');
    $lo = aaLo($paket, $artis);
    $liaisonId = $artis->liaisons()->where('user_id', $lo->id)->value('id');
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.artists.liaisons.release', [$org, $event, $liaisonId]))
        ->assertForbidden();

    $this->actingAs($paket['owner'])
        ->post(route('organizer.events.artists.liaisons.release', [$org, $event, $liaisonId]))
        ->assertRedirect(route('organizer.events.artists.show', [$org, $event, $artis->id]));

    $this->assertSoftDeleted('artist_liaisons', ['id' => $liaisonId]);

    $this->actingAs($lo)
        ->get(route('my.liaison.show', [$artis->id]))
        ->assertNotFound();
    $this->actingAs($lo)
        ->post(route('my.liaison.status', [$artis->id]), ['attendance' => 'arrived'])
        ->assertNotFound();
});

it('toggle rider tanpa assignment 404', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Tanpa LO');
    $bukanLo = aaStafRelawan($paket);

    $this->actingAs($bukanLo)
        ->post(route('my.liaison.rider', [$artis->id]), ['fulfilled' => true])
        ->assertNotFound();
    $this->actingAs($bukanLo)
        ->post(route('my.liaison.note', [$artis->id]), ['body' => 'Catatan nyasar.'])
        ->assertNotFound();

    expect($artis->refresh()->rider_fulfilled)->toBeFalse()
        ->and($artis->notes()->count())->toBe(0);
});

it('catatan append-only: tak ada route edit dan PUT artis tak menyentuh notes', function (): void {
    $paket = aaPaket();
    $artis = aaArtis($paket['event'], $paket['owner'], 'Band Catatan');
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($paket['owner'])
        ->post(route('organizer.events.artists.notes.store', [$org, $event, $artis->id]), ['body' => 'Catatan asli.'])
        ->assertRedirect();

    // Tak ada route PUT untuk notes: metode salah pada URL POST-only → 405.
    $this->actingAs($paket['owner'])
        ->put(route('organizer.events.artists.notes.store', [$org, $event, $artis->id]), ['body' => 'Ubah via PUT.'])
        ->assertStatus(405);

    $this->actingAs($paket['owner'])
        ->put(route('organizer.events.artists.update', [$org, $event, $artis->id]), ['name' => 'Band Catatan Baru'])
        ->assertRedirect();

    expect($artis->refresh()->name)->toBe('Band Catatan Baru')
        ->and($artis->notes()->count())->toBe(1)
        ->and($artis->notes()->first()->body)->toBe('Catatan asli.');
});

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

/** @return array{org: Organization, owner: User, event: Event} */
function asapPaket(): array
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

function asapLo(Event $event): User
{
    $vol = User::factory()->create();
    $role = EventRole::factory()->create(['event_id' => $event->id]);
    Registration::factory()->create([
        'user_id' => $vol->id,
        'event_id' => $event->id,
        'role_id' => $role->id,
        'status' => 'accepted',
    ]);
    $vol->refresh()->givePermissionTo('artist.liaise');

    return $vol->refresh();
}

it('smoke: organizer artist index 200', function () {
    $paket = asapPaket();

    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.artists.index', [$paket['org']->slug, $paket['event']->slug]))
        ->assertOk();
});

it('smoke: LO liaison index 200', function () {
    $paket = asapPaket();
    $artis = app(ArtistService::class)->create($paket['event'], $paket['owner'], ['name' => 'Band Smoke']);
    $lo = asapLo($paket['event']);
    app(ArtistService::class)->assignLiaison($artis, $paket['owner'], $lo);

    $this->actingAs($lo)
        ->get(route('my.liaison.index'))
        ->assertOk();
});

it('smoke: organizer store redirect + flash', function () {
    $paket = asapPaket();

    $respon = $this->actingAs($paket['owner'])
        ->post(route('organizer.events.artists.store', [$paket['org']->slug, $paket['event']->slug]), [
            'name' => 'Band Smoke Baru',
            'scheduled_at' => $paket['event']->start_at->copy()->addHour()->toDateTimeString(),
            'duration_minutes' => 90,
        ]);

    $artis = Artist::where('name', 'Band Smoke Baru')->first();
    expect($artis)->not->toBeNull();
    $respon->assertRedirect(route('organizer.events.artists.show', [$paket['org']->slug, $paket['event']->slug, $artis->id]));
    $respon->assertSessionHas('status', 'Artis ditambahkan.');
});

it('smoke: lintas event 404 + non-LO 404 di my/liaison', function () {
    $paket = asapPaket();
    $artis = app(ArtistService::class)->create($paket['event'], $paket['owner'], ['name' => 'Band Scope']);
    $lo = asapLo($paket['event']);

    // Lintas event: artis event A diakses via event B → 404 (bukan 403).
    $eventLain = Event::factory()->create(['organization_id' => $paket['org']->id]);
    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.artists.show', [$paket['org']->slug, $eventLain->slug, $artis->id]))
        ->assertNotFound();

    // Volunteer accepted tapi bukan LO artis ini → 404 di my/liaison (own-scoped).
    $bukanLo = asapLo($paket['event']);
    $this->actingAs($bukanLo)
        ->get(route('my.liaison.show', [$artis->id]))
        ->assertNotFound();
    $this->actingAs($bukanLo)
        ->post(route('my.liaison.status', [$artis->id]), ['attendance' => 'arrived'])
        ->assertNotFound();

    // LO-nya sendiri bisa akses.
    app(ArtistService::class)->assignLiaison($artis, $paket['owner'], $lo);
    $this->actingAs($lo)
        ->get(route('my.liaison.show', [$artis->id]))
        ->assertOk();
});

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
function aiPaket(string $slug): array
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
    $event = Event::factory()->create(['organization_id' => $org->id, 'slug' => $slug]);

    return ['org' => $org->refresh(), 'owner' => $owner->refresh(), 'event' => $event->refresh()];
}

/** Volunteer accepted event itu + izin LO, siap di-assign. */
function aiSiapLo(array $paket): User
{
    $lo = User::factory()->create();
    $peran = EventRole::factory()->create(['event_id' => $paket['event']->id]);
    Registration::factory()->create([
        'user_id' => $lo->id,
        'event_id' => $paket['event']->id,
        'role_id' => $peran->id,
        'status' => 'accepted',
    ]);
    $lo->refresh()->givePermissionTo('artist.liaise');

    return $lo->refresh();
}

/** @return array{paketA: array<string, mixed>, paketB: array<string, mixed>} */
function aiPasangan(): array
{
    $paketA = aiPaket('festival-artis-a');
    $paketB = aiPaket('festival-artis-b');
    $artisA = app(ArtistService::class)->create($paketA['event'], $paketA['owner'], [
        'name' => 'Band Isolasi A',
        'rider_text' => 'Rider rahasia A.',
    ]);
    $artisB = app(ArtistService::class)->create($paketB['event'], $paketB['owner'], [
        'name' => 'Band Isolasi B',
        'rider_text' => 'Rider rahasia B.',
    ]);
    $loA = aiSiapLo($paketA);
    $loB = aiSiapLo($paketB);
    app(ArtistService::class)->assignLiaison($artisA, $paketA['owner'], $loA);
    app(ArtistService::class)->assignLiaison($artisB, $paketB['owner'], $loB);

    return [
        'paketA' => [...$paketA, 'artis' => $artisA->refresh(), 'lo' => $loA->refresh()],
        'paketB' => [...$paketB, 'artis' => $artisB->refresh(), 'lo' => $loB->refresh()],
    ];
}

it('artis A tak terlihat dari org B dan sebaliknya', function (): void {
    $pair = aiPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];
    $orgA = $paketA['org']->slug;
    $eventA = $paketA['event']->slug;
    $orgB = $paketB['org']->slug;
    $eventB = $paketB['event']->slug;
    $artisA = $paketA['artis'];
    $artisB = $paketB['artis'];

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.artists.index', [$orgB, $eventB]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.artists.show', [$orgB, $eventB, $artisB->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.artists.index', [$orgA, $eventA]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.artists.show', [$orgA, $eventA, $artisA->id]))
        ->assertNotFound();

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.artists.index', [$orgA, $eventA]))
        ->assertOk()
        ->assertSee('Band Isolasi A', false)
        ->assertDontSee('Band Isolasi B', false);
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.artists.index', [$orgB, $eventB]))
        ->assertOk()
        ->assertSee('Band Isolasi B', false)
        ->assertDontSee('Band Isolasi A', false);
});

it('mutasi silang org 404 dan data utuh kedua arah', function (): void {
    $pair = aiPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];
    $orgA = $paketA['org']->slug;
    $eventA = $paketA['event']->slug;
    $orgB = $paketB['org']->slug;
    $eventB = $paketB['event']->slug;
    $artisA = $paketA['artis'];
    $artisB = $paketB['artis'];
    $calonA = aiSiapLo($paketA);

    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.artists.transition', [$orgB, $eventB, $artisB->id]), ['to' => 'soundcheck'])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->put(route('organizer.events.artists.update', [$orgB, $eventB, $artisB->id]), ['name' => 'Band Curi A'])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.artists.assign', [$orgB, $eventB, $artisB->id]), ['user_id' => $calonA->id])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.artists.notes.store', [$orgB, $eventB, $artisB->id]), ['body' => 'Catatan nyasar A.'])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.artists.rider.toggle', [$orgB, $eventB, $artisB->id]), ['fulfilled' => true])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->delete(route('organizer.events.artists.destroy', [$orgB, $eventB, $artisB->id]))
        ->assertNotFound();

    $this->actingAs($paketB['owner'])
        ->post(route('organizer.events.artists.transition', [$orgA, $eventA, $artisA->id]), ['to' => 'soundcheck'])
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->delete(route('organizer.events.artists.destroy', [$orgA, $eventA, $artisA->id]))
        ->assertNotFound();

    expect($artisA->refresh()->status)->toBe('scheduled')
        ->and($artisB->refresh()->status)->toBe('scheduled')
        ->and($artisA->refresh()->name)->toBe('Band Isolasi A')
        ->and($artisB->refresh()->name)->toBe('Band Isolasi B')
        ->and($artisB->refresh()->rider_fulfilled)->toBeFalse()
        ->and($artisB->notes()->count())->toBe(0)
        ->and($artisA->refresh()->trashed())->toBeFalse()
        ->and($artisB->refresh()->trashed())->toBeFalse();
    $this->assertDatabaseHas('artists', ['id' => $artisA->id, 'event_id' => $paketA['event']->id]);
    $this->assertDatabaseHas('artists', ['id' => $artisB->id, 'event_id' => $paketB['event']->id]);
});

it('LO B tak bisa menyentuh artis A dan sebaliknya', function (): void {
    $pair = aiPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];
    $artisA = $paketA['artis'];
    $artisB = $paketB['artis'];
    $loA = $paketA['lo'];
    $loB = $paketB['lo'];

    foreach ([
        [$loA, $artisB],
        [$loB, $artisA],
    ] as [$lo, $artisAsing]) {
        $this->actingAs($lo)
            ->get(route('my.liaison.show', [$artisAsing->id]))
            ->assertNotFound();
        $this->actingAs($lo)
            ->post(route('my.liaison.status', [$artisAsing->id]), ['attendance' => 'arrived'])
            ->assertNotFound();
        $this->actingAs($lo)
            ->post(route('my.liaison.note', [$artisAsing->id]), ['body' => 'Catatan nyasar.'])
            ->assertNotFound();
        $this->actingAs($lo)
            ->post(route('my.liaison.rider', [$artisAsing->id]), ['fulfilled' => true])
            ->assertNotFound();
    }

    expect($artisA->refresh()->status)->toBe('scheduled')
        ->and($artisB->refresh()->status)->toBe('scheduled')
        ->and($artisA->refresh()->attendance)->toBe('expected')
        ->and($artisB->refresh()->attendance)->toBe('expected')
        ->and($artisA->notes()->count())->toBe(0)
        ->and($artisB->notes()->count())->toBe(0);
});

it('index dampingan simetris: tiap LO hanya melihat miliknya', function (): void {
    $pair = aiPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];

    $this->actingAs($paketA['lo'])
        ->get(route('my.liaison.index'))
        ->assertOk()
        ->assertSee('Band Isolasi A', false)
        ->assertDontSee('Band Isolasi B', false);
    $this->actingAs($paketB['lo'])
        ->get(route('my.liaison.index'))
        ->assertOk()
        ->assertSee('Band Isolasi B', false)
        ->assertDontSee('Band Isolasi A', false);
});

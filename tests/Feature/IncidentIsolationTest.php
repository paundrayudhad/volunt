<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Incident;
use App\Models\LostFoundItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\IncidentService;
use App\Services\LostFoundService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, insiden: Incident, item: LostFoundItem, klaim: LostFoundItem} */
function g5iPaket(string $slug): array
{
    $org = Organization::factory()->create(['status' => 'active', 'slug' => 'org-isolasi-'.$slug]);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($owner->refresh(), $org);
    $event = Event::factory()->create(['organization_id' => $org->id, 'slug' => 'event-isolasi-'.$slug]);

    $svc = app(IncidentService::class);
    $insiden = $svc->report($event->refresh(), $owner->refresh(), [
        'category' => 'security',
        'priority' => 'high',
        'location' => 'Pintu isolasi '.$slug,
        'description' => 'Keributan di antrean pintu masuk isolasi '.$slug.'.',
    ]);
    $svc->assign($insiden->refresh(), $owner->refresh(), $owner->refresh());

    $temuan = app(LostFoundService::class);
    $item = $temuan->report($event->refresh(), $owner->refresh(), [
        'kind' => 'found',
        'item_name' => 'Tas selempang '.$slug,
        'location' => 'Posko isolasi '.$slug,
    ]);
    $pengklaim = User::factory()->create();
    $klaim = $temuan->claim($item->refresh(), $pengklaim);

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'insiden' => $insiden->refresh(),
        'item' => $item->refresh(),
        'klaim' => $klaim->refresh(),
    ];
}

function g5iFoto(): UploadedFile
{
    $biner = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $tmp = tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($tmp, $biner);

    return new UploadedFile($tmp, 'barang.png', 'image/png', null, true);
}

/** @return array{paketA: array<string, mixed>, paketB: array<string, mixed>} */
function g5iPasangan(): array
{
    return ['paketA' => g5iPaket('a'), 'paketB' => g5iPaket('b')];
}

it('index org A hanya milik A', function (): void {
    $pair = g5iPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.incidents.index', [$paketA['org']->slug, $paketA['event']->slug]))
        ->assertOk()
        ->assertSee('Pintu isolasi a')
        ->assertDontSee('Pintu isolasi b');

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.lost_found.index', [$paketA['org']->slug, $paketA['event']->slug]))
        ->assertOk()
        ->assertSee('Tas selempang a')
        ->assertDontSee('Tas selempang b');

    expect(Incident::where('event_id', $paketA['event']->id)->count())->toBe(1)
        ->and(LostFoundItem::where('event_id', $paketA['event']->id)->count())->toBe(1)
        ->and(LostFoundItem::where('event_id', $paketB['event']->id)->count())->toBe(1);
});

it('read silang insiden item 404 kedua arah', function (): void {
    $pair = g5iPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.incidents.show', [$paketA['org']->slug, $paketA['event']->slug, $paketB['insiden']->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.incidents.show', [$paketB['org']->slug, $paketB['event']->slug, $paketA['insiden']->id]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.lost_found.show', [$paketA['org']->slug, $paketA['event']->slug, $paketB['item']->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.lost_found.show', [$paketB['org']->slug, $paketB['event']->slug, $paketA['item']->id]))
        ->assertNotFound();
});

it('history klaim tak tercampur antar org', function (): void {
    $pair = g5iPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];

    $riwayatA = $paketA['insiden']->histories()->pluck('to_status')->all();
    $riwayatB = $paketB['insiden']->histories()->pluck('to_status')->all();

    expect($riwayatA)->toBe(['assigned'])
        ->and($riwayatB)->toBe(['assigned'])
        ->and($paketA['insiden']->histories()->where('incident_id', $paketB['insiden']->id)->count())->toBe(0);

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.incidents.show', [$paketA['org']->slug, $paketA['event']->slug, $paketA['insiden']->id]))
        ->assertOk()
        ->assertSee('assigned')
        ->assertDontSee('Pintu isolasi b');

    $klaimAwalA = $paketA['klaim']->claimant_id;
    app(LostFoundService::class)->resolveClaim($paketB['klaim']->refresh(), $paketB['owner'], 'returned', 'Ciri cocok dengan laporan.');

    expect($paketB['klaim']->refresh()->status)->toBe('returned')
        ->and($paketA['klaim']->refresh()->status)->toBe('claimed')
        ->and($paketA['klaim']->refresh()->claimant_id)->toBe($klaimAwalA);
});

it('foto A tak bisa diakses member B', function (): void {
    Storage::fake('local');
    $pair = g5iPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];

    $itemFoto = app(LostFoundService::class)->report($paketA['event'], $paketA['owner'], [
        'kind' => 'found',
        'item_name' => 'Dompet foto isolasi',
        'location' => 'Posko foto isolasi',
        'photo' => g5iFoto(),
    ]);
    $url = URL::signedRoute('lostfound.photo', ['lostFoundItem' => $itemFoto->id], now()->addMinutes(30));

    $anggotaB = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $paketB['org']->id,
        'user_id' => $anggotaB->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $relawanAsing = User::factory()->create();
    $roleAsing = EventRole::factory()->create(['event_id' => $paketB['event']->id]);
    Registration::factory()->create([
        'user_id' => $relawanAsing->id,
        'event_id' => $paketB['event']->id,
        'role_id' => $roleAsing->id,
        'status' => 'accepted',
    ]);

    $this->actingAs($anggotaB->refresh())->get($url)->assertNotFound();
    $this->actingAs($relawanAsing->refresh())->get($url)->assertNotFound();
    $this->actingAs($paketA['owner'])->get($url)->assertOk();
});

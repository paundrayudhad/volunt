<?php

use App\Models\Event;
use App\Models\EventRole;
use App\Models\Incident;
use App\Models\LostFoundItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
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

/** @return array{org: Organization, owner: User, event: Event, staf: User} */
function g5aPaket(): array
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

function g5aStafLapor(array $paket, string $email): User
{
    $staf = User::factory()->create(['email' => $email]);
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $paket['org']->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staf->refresh(), $paket['org']);
    $staf->refresh()->givePermissionTo('incident.report');

    return $staf->refresh();
}

function g5aInsiden(Event $event, User $owner): Incident
{
    return Incident::factory()->create([
        'event_id' => $event->id,
        'reporter_id' => $owner->id,
        'status' => 'open',
    ]);
}

function g5aItem(Event $event, User $owner): LostFoundItem
{
    return LostFoundItem::factory()->create([
        'event_id' => $event->id,
        'reporter_id' => $owner->id,
        'kind' => 'found',
        'status' => 'found',
    ]);
}

function g5aFoto(): UploadedFile
{
    $biner = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $tmp = tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($tmp, $biner);

    return new UploadedFile($tmp, 'barang.png', 'image/png', null, true);
}

function g5aVolunteer(Event $event): User
{
    $vol = User::factory()->create();
    $role = EventRole::factory()->create(['event_id' => $event->id]);
    Registration::factory()->create([
        'user_id' => $vol->id,
        'event_id' => $event->id,
        'role_id' => $role->id,
        'status' => 'accepted',
    ]);

    return $vol->refresh();
}

/** @return array<string, string> */
function g5aInsidenBaru(): array
{
    return [
        'category' => 'medical',
        'priority' => 'high',
        'location' => 'Depan panggung',
        'description' => 'Penonton jatuh pingsan di depan panggung.',
    ];
}

it('guest insiden lostfound diarahkan ke login', function (): void {
    Storage::fake('local');
    $paket = g5aPaket();
    $item = app(LostFoundService::class)->report($paket['event'], $paket['owner'], [
        'kind' => 'found',
        'item_name' => 'Dompet',
        'location' => 'Posko informasi',
        'photo' => g5aFoto(),
    ]);
    $foto = URL::signedRoute('lostfound.photo', ['lostFoundItem' => $item->id], now()->addMinutes(30));
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->get(route('organizer.events.incidents.index', [$org, $event]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.incidents.store', [$org, $event]), g5aInsidenBaru())->assertRedirect(route('login'));
    $this->get(route('organizer.events.lost_found.index', [$org, $event]))->assertRedirect(route('login'));
    $this->get($foto)->assertRedirect(route('login'));

    expect(Incident::count())->toBe(0);
});

it('staf basis 403 semua mutasi insiden lostfound', function (): void {
    $paket = g5aPaket();
    $insiden = g5aInsiden($paket['event'], $paket['owner']);
    $item = g5aItem($paket['event'], $paket['owner']);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($paket['staf'])
        ->get(route('organizer.events.incidents.index', [$org, $event]))
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.incidents.store', [$org, $event]), g5aInsidenBaru())
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.incidents.assign', [$org, $event, $insiden->id]), ['assignee_id' => $paket['staf']->id])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.incidents.transition', [$org, $event, $insiden->id]), ['to' => 'assigned'])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.incidents.reopen', [$org, $event, $insiden->id]), ['reason' => 'Kerusakan muncul lagi saat gladi.'])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->delete(route('organizer.events.incidents.destroy', [$org, $event, $insiden->id]))
        ->assertForbidden();

    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.lost_found.store', [$org, $event]), [
            'kind' => 'found',
            'item_name' => 'Kunci motor',
            'location' => 'Posko informasi',
        ])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.lost_found.resolve', [$org, $event, $item->id]), [
            'decision' => 'returned',
            'note' => 'Ciri cocok dengan laporan.',
        ])
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->post(route('organizer.events.lost_found.close', [$org, $event, $item->id]))
        ->assertForbidden();
    $this->actingAs($paket['staf'])
        ->delete(route('organizer.events.lost_found.destroy', [$org, $event, $item->id]))
        ->assertForbidden();

    expect($insiden->refresh()->status)->toBe('open')
        ->and($item->refresh()->status)->toBe('found')
        ->and(Incident::where('event_id', $paket['event']->id)->count())->toBe(1);
});

it('staf pelapor bisa store tapi 403 kelola', function (): void {
    $paket = g5aPaket();
    $pelapor = g5aStafLapor($paket, 'staf-lapor@gate.test');
    $insiden = g5aInsiden($paket['event'], $paket['owner']);
    $item = g5aItem($paket['event'], $paket['owner']);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($pelapor)
        ->post(route('organizer.events.incidents.store', [$org, $event]), g5aInsidenBaru())
        ->assertRedirect();

    $this->actingAs($pelapor)
        ->post(route('organizer.events.incidents.assign', [$org, $event, $insiden->id]), ['assignee_id' => $pelapor->id])
        ->assertForbidden();
    $this->actingAs($pelapor)
        ->post(route('organizer.events.incidents.transition', [$org, $event, $insiden->id]), ['to' => 'assigned'])
        ->assertForbidden();
    $this->actingAs($pelapor)
        ->post(route('organizer.events.incidents.reopen', [$org, $event, $insiden->id]), ['reason' => 'Kerusakan muncul lagi saat gladi.'])
        ->assertForbidden();
    $this->actingAs($pelapor)
        ->delete(route('organizer.events.incidents.destroy', [$org, $event, $insiden->id]))
        ->assertForbidden();
    $this->actingAs($pelapor)
        ->post(route('organizer.events.lost_found.resolve', [$org, $event, $item->id]), ['decision' => 'returned'])
        ->assertForbidden();

    expect(Incident::where('event_id', $paket['event']->id)->count())->toBe(2)
        ->and($insiden->refresh()->status)->toBe('open');
});

it('lintas org insiden lostfound 404', function (): void {
    $paketA = g5aPaket();
    $paketB = g5aPaket();
    $insidenB = g5aInsiden($paketB['event'], $paketB['owner']);
    $itemB = g5aItem($paketB['event'], $paketB['owner']);
    $orgB = $paketB['org']->slug;
    $eventB = $paketB['event']->slug;

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.incidents.show', [$orgB, $eventB, $insidenB->id]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.incidents.transition', [$orgB, $eventB, $insidenB->id]), ['to' => 'assigned'])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.lost_found.show', [$orgB, $eventB, $itemB->id]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->post(route('organizer.events.lost_found.resolve', [$orgB, $eventB, $itemB->id]), ['decision' => 'returned'])
        ->assertNotFound();

    expect($insidenB->refresh()->status)->toBe('open')
        ->and($itemB->refresh()->status)->toBe('found');
});

it('lintas event satu org 404', function (): void {
    $paket = g5aPaket();
    $eventLain = Event::factory()->create(['organization_id' => $paket['org']->id]);
    $insiden = g5aInsiden($eventLain, $paket['owner']);
    $item = g5aItem($eventLain, $paket['owner']);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.incidents.show', [$org, $event, $insiden->id]))
        ->assertNotFound();
    $this->actingAs($paket['owner'])
        ->post(route('organizer.events.incidents.assign', [$org, $event, $insiden->id]), ['assignee_id' => $paket['staf']->id])
        ->assertNotFound();
    $this->actingAs($paket['owner'])
        ->get(route('organizer.events.lost_found.show', [$org, $event, $item->id]))
        ->assertNotFound();

    expect($insiden->refresh()->status)->toBe('open');
});

it('volunteer di rute organizer 404 luar scope', function (): void {
    $paket = g5aPaket();
    $vol = g5aVolunteer($paket['event']);
    $insiden = g5aInsiden($paket['event'], $paket['owner']);
    $item = g5aItem($paket['event'], $paket['owner']);
    $org = $paket['org']->slug;
    $event = $paket['event']->slug;

    $this->actingAs($vol)
        ->get(route('organizer.events.incidents.index', [$org, $event]))
        ->assertNotFound();
    $this->actingAs($vol)
        ->get(route('organizer.events.incidents.show', [$org, $event, $insiden->id]))
        ->assertNotFound();
    $this->actingAs($vol)
        ->post(route('organizer.events.incidents.assign', [$org, $event, $insiden->id]), ['assignee_id' => $vol->id])
        ->assertNotFound();
    $this->actingAs($vol)
        ->post(route('organizer.events.incidents.transition', [$org, $event, $insiden->id]), ['to' => 'assigned'])
        ->assertNotFound();
    $this->actingAs($vol)
        ->delete(route('organizer.events.incidents.destroy', [$org, $event, $insiden->id]))
        ->assertNotFound();
    $this->actingAs($vol)
        ->get(route('organizer.events.lost_found.index', [$org, $event]))
        ->assertNotFound();
    $this->actingAs($vol)
        ->post(route('organizer.events.lost_found.resolve', [$org, $event, $item->id]), ['decision' => 'returned'])
        ->assertNotFound();
    $this->actingAs($vol)
        ->post(route('organizer.events.lost_found.close', [$org, $event, $item->id]))
        ->assertNotFound();

    expect($insiden->refresh()->status)->toBe('open')
        ->and($item->refresh()->status)->toBe('found');
});

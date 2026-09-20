<?php

use App\Models\Event;
use App\Models\Incident;
use App\Models\LostFoundItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\LostFoundService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, staf: User} */
function insWebSetup(): array
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

/** @return array<int, mixed> */
function insWebParam(array $s, ?Incident $insiden = null): array
{
    $param = [$s['org']->slug, $s['event']->slug];
    if ($insiden instanceof Incident) {
        $param[] = $insiden->id;
    }

    return $param;
}

it('organizer lapor insiden lalu transisi via http', function (): void {
    $s = insWebSetup();

    $res = $this->actingAs($s['owner'])->post(route('organizer.events.incidents.store', [$s['org']->slug, $s['event']->slug]), [
        'category' => 'security',
        'priority' => 'critical',
        'location' => 'Pintu A',
        'description' => 'Keributan di antrean pintu masuk A.',
    ]);
    $res->assertRedirect();

    $insiden = Incident::where('event_id', $s['event']->id)->firstOrFail();

    $this->actingAs($s['owner'])->post(route('organizer.events.incidents.assign', insWebParam($s, $insiden)), [
        'assignee_id' => $s['staf']->id,
    ])->assertRedirect();

    $this->actingAs($s['owner'])->post(route('organizer.events.incidents.transition', insWebParam($s, $insiden)), [
        'to' => 'in_progress',
    ])->assertRedirect();

    expect($insiden->refresh()->status)->toBe('in_progress');
});

it('lompat tahap via http 422 dengan pesan', function (): void {
    $s = insWebSetup();
    $insiden = Incident::factory()->create([
        'event_id' => $s['event']->id,
        'reporter_id' => $s['owner']->id,
        'status' => 'open',
    ]);
    $show = route('organizer.events.incidents.show', insWebParam($s, $insiden));

    $this->actingAs($s['owner'])->from($show)->post(route('organizer.events.incidents.transition', insWebParam($s, $insiden)), [
        'to' => 'resolved',
    ])->assertRedirect($show);

    $this->actingAs($s['owner'])->get($show)->assertOk()->assertSee('Tahap berikutnya');

    expect($insiden->refresh()->status)->toBe('open');
});

it('index filter status dan badge critical tampil', function (): void {
    $s = insWebSetup();
    Incident::factory()->create([
        'event_id' => $s['event']->id,
        'reporter_id' => $s['owner']->id,
        'priority' => 'critical',
        'location' => 'Pintu A',
        'status' => 'open',
    ]);
    Incident::factory()->create([
        'event_id' => $s['event']->id,
        'reporter_id' => $s['owner']->id,
        'priority' => 'low',
        'location' => 'Pintu B',
        'status' => 'open',
    ]);

    $this->actingAs($s['owner'])
        ->get(route('organizer.events.incidents.index', [$s['org']->slug, $s['event']->slug, 'status' => 'open']))
        ->assertOk()
        ->assertSee('Pintu A')
        ->assertSee('Pintu B')
        ->assertSee('KRITIS');
});

it('insiden lintas event 404', function (): void {
    $s = insWebSetup();
    $eventLain = Event::factory()->create(['organization_id' => $s['org']->id]);
    $insiden = Incident::factory()->create([
        'event_id' => $eventLain->id,
        'reporter_id' => $s['owner']->id,
    ]);

    $this->actingAs($s['owner'])
        ->get(route('organizer.events.incidents.show', [$s['org']->slug, $s['event']->slug, $insiden->id]))
        ->assertNotFound();
});

it('staff read-only 403 saat assign', function (): void {
    $s = insWebSetup();
    $insiden = Incident::factory()->create([
        'event_id' => $s['event']->id,
        'reporter_id' => $s['owner']->id,
        'status' => 'open',
    ]);

    $this->actingAs($s['staf'])->post(route('organizer.events.incidents.assign', insWebParam($s, $insiden)), [
        'assignee_id' => $s['staf']->id,
    ])->assertForbidden();

    expect($insiden->refresh()->status)->toBe('open');
});

it('lostfound lapor dan resolve klaim via http', function (): void {
    $s = insWebSetup();

    $res = $this->actingAs($s['owner'])->post(route('organizer.events.lost_found.store', [$s['org']->slug, $s['event']->slug]), [
        'kind' => 'found',
        'item_name' => 'Kunci motor',
        'location' => 'Posko informasi',
    ]);
    $res->assertRedirect();

    $item = LostFoundItem::where('event_id', $s['event']->id)->firstOrFail();
    expect($item->status)->toBe('found');

    $pengklaim = User::factory()->create();
    app(LostFoundService::class)->claim($item->refresh(), $pengklaim);

    $this->actingAs($s['owner'])->post(route('organizer.events.lost_found.resolve', [$s['org']->slug, $s['event']->slug, $item->id]), [
        'decision' => 'returned',
        'note' => 'Ciri cocok dengan laporan.',
    ])->assertRedirect();

    expect($item->refresh()->status)->toBe('returned');
});

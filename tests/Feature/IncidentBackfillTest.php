<?php

use App\Models\Event;
use App\Models\LostFoundItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\IncidentService;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, staf: User, event: Event} */
function g5bPaketKetahanan(): array
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
    $owner->givePermissionTo(['incident.manage', 'incident.report', 'lostfound.manage']);
    $staf = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $event = Event::factory()->create(['organization_id' => $org->id]);

    return ['org' => $org->refresh(), 'owner' => $owner->refresh(), 'staf' => $staf->refresh(), 'event' => $event->refresh()];
}

it('backfill memberi perm insiden ke owner lama', function () {
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $owner->givePermissionTo(['incident.manage', 'incident.report', 'lostfound.manage']);
    Event::factory()->create(['organization_id' => $org->id]);
    $owner->revokePermissionTo(['incident.manage', 'incident.report', 'lostfound.manage']);
    expect($owner->refresh()->can('incident.manage'))->toBeFalse();

    (require base_path('database/migrations/2026_09_20_000006_backfill_incident_permissions.php'))->up();

    expect($owner->refresh()->can('incident.manage'))->toBeTrue()
        ->and($owner->can('incident.report'))->toBeTrue()
        ->and($owner->can('lostfound.manage'))->toBeTrue();
});

it('backfill tak memberi perm ke non-owner', function () {
    $paket = g5bPaketKetahanan();
    $paket['staf']->givePermissionTo('incident.report');
    $paket['staf']->revokePermissionTo('incident.report');

    (require base_path('database/migrations/2026_09_20_000006_backfill_incident_permissions.php'))->up();

    expect($paket['staf']->refresh()->can('incident.manage'))->toBeFalse()
        ->and($paket['staf']->can('incident.report'))->toBeFalse()
        ->and($paket['staf']->can('lostfound.manage'))->toBeFalse()
        ->and($paket['owner']->refresh()->can('incident.manage'))->toBeTrue();
});

it('report dengan item event lain ditolak', function () {
    $paket = g5bPaketKetahanan();
    $eventLain = Event::factory()->create(['organization_id' => $paket['org']->id]);
    $itemLain = LostFoundItem::factory()->create(['event_id' => $eventLain->id, 'kind' => 'found']);

    try {
        app(IncidentService::class)->report($paket['event'], $paket['owner'], [
            'category' => 'lost_found',
            'priority' => 'medium',
            'location' => 'Posko informasi',
            'description' => 'Penonton melaporkan dompet hilang di area konser.',
            'lost_found_item_id' => $itemLain->id,
        ]);
        $this->fail('Harusnya menolak item event lain.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Item tertaut bukan milik event ini.');
    }
});

it('assign ke non-member ditolak', function () {
    $paket = g5bPaketKetahanan();
    $insiden = app(IncidentService::class)->report($paket['event'], $paket['owner'], [
        'category' => 'security',
        'priority' => 'high',
        'location' => 'Pintu A',
        'description' => 'Keributan di antrean pintu masuk A.',
    ]);
    $luar = User::factory()->create();

    try {
        app(IncidentService::class)->assign($insiden, $paket['owner'], $luar);
        $this->fail('Harusnya menolak non-member.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toBe('Petugas harus member organisasi yang sama.');
    }
});

it('hapus item menullkan link insiden pada force-delete, insiden tetap ada', function () {
    $paket = g5bPaketKetahanan();
    $item = LostFoundItem::factory()->create(['event_id' => $paket['event']->id, 'kind' => 'found']);
    $insiden = app(IncidentService::class)->report($paket['event'], $paket['owner'], [
        'category' => 'lost_found',
        'priority' => 'medium',
        'location' => 'Posko informasi',
        'description' => 'Penonton melaporkan dompet hilang di area konser.',
        'lost_found_item_id' => $item->id,
    ]);

    $item->forceDelete();

    expect($insiden->refresh()->exists)->toBeTrue()
        ->and($insiden->refresh()->lost_found_item_id)->toBeNull();
});

it('soft-delete item mempertahankan link insiden', function () {
    $paket = g5bPaketKetahanan();
    $item = LostFoundItem::factory()->create(['event_id' => $paket['event']->id, 'kind' => 'found']);
    $insiden = app(IncidentService::class)->report($paket['event'], $paket['owner'], [
        'category' => 'lost_found',
        'priority' => 'medium',
        'location' => 'Posko informasi',
        'description' => 'Penonton melaporkan dompet hilang di area konser.',
        'lost_found_item_id' => $item->id,
    ]);

    $item->delete();

    expect($insiden->refresh()->exists)->toBeTrue()
        ->and($insiden->refresh()->lost_found_item_id)->toBe($item->id)
        ->and($item->refresh()->trashed())->toBeTrue();
});

it('hapus insiden, item tetap ada', function () {
    $paket = g5bPaketKetahanan();
    $item = LostFoundItem::factory()->create(['event_id' => $paket['event']->id, 'kind' => 'found']);
    $insiden = app(IncidentService::class)->report($paket['event'], $paket['owner'], [
        'category' => 'lost_found',
        'priority' => 'medium',
        'location' => 'Posko informasi',
        'description' => 'Penonton melaporkan dompet hilang di area konser.',
        'lost_found_item_id' => $item->id,
    ]);

    $insiden->delete();

    expect($item->refresh()->exists)->toBeTrue();
});

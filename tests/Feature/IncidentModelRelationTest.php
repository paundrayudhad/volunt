<?php

use App\Models\Event;
use App\Models\Incident;
use App\Models\LostFoundItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function buatEventInsiden(): Event
{
    return Event::factory()->create();
}

it('insiden terhubung event dan reporter', function () {
    $event = buatEventInsiden();
    $pelapor = User::factory()->create();
    $insiden = Incident::factory()->create(['event_id' => $event->id, 'reporter_id' => $pelapor->id]);

    expect($insiden->event->id)->toBe($event->id)
        ->and($insiden->reporter->id)->toBe($pelapor->id)
        ->and($event->incidents()->count())->toBe(1);
});

it('permission granular 5B terdaftar', function () {
    foreach (['incident.manage', 'incident.report', 'lostfound.manage'] as $nama) {
        expect(Permission::where('name', $nama)->exists())->toBeTrue();
    }
});

it('item lost&found terhubung event', function () {
    $event = buatEventInsiden();
    $item = LostFoundItem::factory()->create(['event_id' => $event->id, 'kind' => 'found']);

    expect($item->event->id)->toBe($event->id)
        ->and($event->lostFoundItems()->count())->toBe(1);
});

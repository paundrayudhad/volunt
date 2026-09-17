<?php

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('event milik organisasi dengan relasi division role shift', function (): void {
    $org = Organization::factory()->create();
    $event = Event::factory()->for($org)->create();
    $div = EventDivision::factory()->for($event)->create();
    $role = EventRole::factory()->for($event)->for($div, 'division')->create();
    $shift = EventShift::factory()->for($event)->for($div, 'division')->for($role, 'role')->create();

    expect($event->organization->is($org))->toBeTrue()
        ->and($event->divisions)->toHaveCount(1)
        ->and($role->shifts)->toHaveCount(1)
        ->and($org->events->first()->is($event))->toBeTrue();
});

it('slug unik per organisasi tetapi boleh sama lintas organisasi', function (): void {
    $a = Organization::factory()->create();
    $b = Organization::factory()->create();
    Event::factory()->for($a)->create(['slug' => 'festival']);
    Event::factory()->for($b)->create(['slug' => 'festival']);

    expect(fn () => Event::factory()->for($a)->create(['slug' => 'festival']))
        ->toThrow(QueryException::class);
});

it('check constraint menolak end_at sebelum start_at dan accepted_count melebihi quota', function (): void {
    $event = Event::factory()->create();
    $div = EventDivision::factory()->for($event)->create();

    expect(fn () => DB::transaction(fn () => Event::factory()->create([
        'start_at' => now()->addDay(), 'end_at' => now(),
    ])))->toThrow(QueryException::class);

    $role = EventRole::factory()->for($event)->for($div, 'division')->create(['quota' => 2]);
    expect(fn () => DB::transaction(fn () => $role->forceFill(['accepted_count' => 3])->save()))
        ->toThrow(QueryException::class);
});

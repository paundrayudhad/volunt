<?php

use App\Exceptions\QuotaFullException;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\QuotaService;

/** @return array{org: Organization, owner: User, event: Event, role: EventRole} */
function quotaTesSetup(int $kuota = 2): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $event->forceFill(['status' => 'registration_open'])->save();
    $divisi = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $divisi->id,
        'quota' => $kuota,
        'accepted_count' => 0,
    ]);

    return ['org' => $org, 'owner' => $owner, 'event' => $event->refresh(), 'role' => $role->refresh()];
}

it('accept menaikkan counter saat slot tersedia', function (): void {
    $s = quotaTesSetup(2);

    app(QuotaService::class)->accept($s['role']);

    expect($s['role']->refresh()->accepted_count)->toBe(1);
});

it('accept penuh melempar QuotaFullException', function (): void {
    $s = quotaTesSetup(1);
    app(QuotaService::class)->accept($s['role']);

    try {
        app(QuotaService::class)->accept($s['role']->refresh());
        $this->fail('Seharusnya melempar QuotaFullException.');
    } catch (QuotaFullException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($s['role']->refresh()->accepted_count)->toBe(1);
    }
});

it('release menurunkan counter dan tidak negatif', function (): void {
    $s = quotaTesSetup(2);
    $svc = app(QuotaService::class);

    $svc->accept($s['role']);
    $svc->release($s['role']->refresh());

    expect($s['role']->refresh()->accepted_count)->toBe(0);

    $svc->release($s['role']->refresh());

    expect($s['role']->refresh()->accepted_count)->toBe(0);
});

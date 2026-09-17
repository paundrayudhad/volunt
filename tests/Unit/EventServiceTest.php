<?php

use App\Models\EventShift;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\EventService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function eventTesOwnerAktif(): array
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

    return [$owner->refresh(), $org->refresh()];
}

function eventTesSetup(): array
{
    [$owner, $org] = eventTesOwnerAktif();
    $event = app(EventService::class)->createEvent($org, [
        'name' => 'Festival',
        'slug' => 'festival',
        'start_at' => now()->addMonth(),
        'end_at' => now()->addMonth()->addDays(2),
    ], $owner);

    return [$owner, $org, $event];
}

it('create event dalam org aktif menghasilkan draft + audit', function (): void {
    [$owner, $org] = eventTesOwnerAktif();
    $event = app(EventService::class)->createEvent($org, [
        'name' => 'Festival',
        'slug' => 'festival',
        'start_at' => now()->addMonth(),
        'end_at' => now()->addMonth()->addDays(2),
    ], $owner);

    expect($event->status)->toBe('draft')
        ->and($event->organization_id)->toBe($org->id);
    $this->assertDatabaseHas('audit_logs', ['action' => 'event.created']);
});

it('transition valid draft sampai archived lolos berurutan', function (): void {
    [$owner, $org, $event] = eventTesSetup();
    $svc = app(EventService::class);
    foreach (['published', 'registration_open', 'registration_closed', 'ongoing', 'completed', 'archived'] as $next) {
        $event = $svc->transitionTo($event, $next, $owner);
        expect($event->status)->toBe($next);
    }
});

it('transition invalid dan cancel tanpa alasan ditolak', function (): void {
    [$owner, $org, $event] = eventTesSetup();
    $svc = app(EventService::class);

    expect(fn () => $svc->transitionTo($event, 'completed', $owner))
        ->toThrow(HttpException::class); // draft→completed invalid → 422
    expect(fn () => $svc->transitionTo($event, 'cancelled', $owner))
        ->toThrow(HttpException::class); // tanpa reason → 422
    $event = $svc->transitionTo($event, 'cancelled', $owner, 'Sponsor mundur');
    expect($event->status)->toBe('cancelled');
    expect(fn () => $svc->transitionTo($event, 'draft', $owner))
        ->toThrow(HttpException::class); // terminal tak bisa keluar
});

it('cancelled dan archived menolak edit dan operasi division', function (): void {
    [$owner, $org, $event] = eventTesSetup();
    $svc = app(EventService::class);
    $svc->transitionTo($event, 'cancelled', $owner, 'Bencana alam');

    expect(fn () => $svc->updateEvent($event->fresh(), ['name' => 'Baru'], $owner))
        ->toThrow(HttpException::class);
    expect(fn () => $svc->createDivision($event->fresh(), ['name' => 'Stage'], $owner))
        ->toThrow(HttpException::class);
});

it('crud division role shift ter-scope event + sisa kuota', function (): void {
    [$owner, $org, $event] = eventTesSetup();
    $svc = app(EventService::class);
    $div = $svc->createDivision($event, ['name' => 'Stage'], $owner);
    $role = $svc->createRole($event, $div, ['name' => 'Usher', 'quota' => 5], $owner);
    $shift = $svc->createShift($event, $div, $role, [
        'start_at' => $event->start_at,
        'end_at' => $event->end_at,
    ], $owner);

    expect($role->remainingQuota())->toBe(5)
        ->and($shift->event_id)->toBe($event->id);
    $svc->deleteShift($shift, $owner);
    expect(EventShift::find($shift->id))->toBeNull();
});

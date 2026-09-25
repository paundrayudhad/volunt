<?php

use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('talent.search', 'web');
    Permission::findOrCreate('talent.invite', 'web');
});

it('organizer dapat mengirim undangan ke relawan', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    $owner->givePermissionTo(['talent.invite']);

    $volunteer = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'published']);
    $role = EventRole::factory()->create(['event_id' => $event->id, 'quota' => 10, 'accepted_count' => 0]);

    $service = app(InvitationService::class);
    $invitation = $service->invite($event, $owner, $volunteer, $role->id, 'Ayo gabung event kami!');

    expect($invitation)->toBeInstanceOf(EventInvitation::class)
        ->and($invitation->status)->toBe('pending')
        ->and($invitation->event_id)->toBe($event->id)
        ->and($invitation->user_id)->toBe($volunteer->id)
        ->and($invitation->role_id)->toBe($role->id)
        ->and($invitation->message)->toBe('Ayo gabung event kami!');
});

it('mencegah pengiriman undangan jika sudah ada undangan pending yang sama', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    $volunteer = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'published']);

    $service = app(InvitationService::class);
    $service->invite($event, $owner, $volunteer, null, 'Undangan pertama');

    $service->invite($event, $owner, $volunteer, null, 'Undangan kedua');
})->throws(HttpException::class);

it('relawan dapat menerima undangan dan otomatis terdaftar serta memotong kuota role', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    $volunteer = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'published']);
    $role = EventRole::factory()->create(['event_id' => $event->id, 'quota' => 5, 'accepted_count' => 0]);

    $service = app(InvitationService::class);
    $invitation = $service->invite($event, $owner, $volunteer, $role->id);

    $responded = $service->respond($invitation, $volunteer, 'accepted');

    expect($responded->status)->toBe('accepted')
        ->and($responded->responded_at)->not->toBeNull();

    $role->refresh();
    expect($role->accepted_count)->toBe(1);

    $registration = Registration::where('event_id', $event->id)->where('user_id', $volunteer->id)->first();
    expect($registration)->not->toBeNull()
        ->and($registration->status)->toBe('accepted')
        ->and($registration->role_id)->toBe($role->id);
});

it('relawan dapat menolak undangan', function () {
    $org = Organization::factory()->create(['status' => 'active']);
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));

    $volunteer = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'published']);

    $service = app(InvitationService::class);
    $invitation = $service->invite($event, $owner, $volunteer);

    $responded = $service->respond($invitation, $volunteer, 'declined');

    expect($responded->status)->toBe('declined')
        ->and($responded->responded_at)->not->toBeNull();

    $hasRegistration = Registration::where('event_id', $event->id)->where('user_id', $volunteer->id)->exists();
    expect($hasRegistration)->toBeFalse();
});

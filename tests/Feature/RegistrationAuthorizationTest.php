<?php

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, role: EventRole} */
function regAuthzPackage(): array
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
    $event->forceFill(['status' => 'registration_open'])->save();
    $division = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'quota' => 5,
        'accepted_count' => 0,
    ]);

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'role' => $role->refresh(),
    ];
}

function regAuthzStaff(array $package, string $email): User
{
    $staff = User::factory()->create(['email' => $email]);
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $package['org']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staff->refresh(), $package['org']);

    return $staff->refresh();
}

function regAuthzRegistration(array $package, ?User $volunteer = null, string $status = 'under_review'): Registration
{
    $member = $volunteer ?? User::factory()->create();

    return Registration::unguarded(fn (): Registration => Registration::create([
        'user_id' => $member->id,
        'event_id' => $package['event']->id,
        'role_id' => $package['role']->id,
        'status' => $status,
        'submitted_at' => now(),
        'idempotency_key' => (string) Str::uuid(),
    ]))->refresh();
}

/** @return array<string, int> */
function regAuthzConfirm(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('guest diarahkan ke login pada seluruh rute registrasi', function (): void {
    $package = regAuthzPackage();
    $registration = regAuthzRegistration($package);
    $volunteer = User::factory()->create();
    VolunteerProfile::factory()->create(['user_id' => $volunteer->id]);

    $this->get(route('registrations.index'))->assertRedirect(route('login'));
    $this->get(route('registrations.show', $registration->id))->assertRedirect(route('login'));
    $this->get(route('registrations.create', $package['event']->slug))->assertRedirect(route('login'));
    $this->post(route('registrations.store', $package['event']->slug), ['role_id' => $package['role']->id])->assertRedirect(route('login'));
    $this->post(route('registrations.withdraw', $registration->id))->assertRedirect(route('login'));
    $this->get(route('profile.volunteer.edit'))->assertRedirect(route('login'));
    $this->patch(route('profile.volunteer.update'), ['full_name' => 'Coba'])->assertRedirect(route('login'));
    $this->get(route('organizer.events.registrations.index', [$package['org']->slug, $package['event']->slug]))->assertRedirect(route('login'));
    $this->get(route('organizer.events.registrations.show', [$package['org']->slug, $package['event']->slug, $registration->id]))->assertRedirect(route('login'));
    $this->post(route('organizer.events.registrations.review', [$package['org']->slug, $package['event']->slug, $registration->id]), ['action' => 'accepted'])->assertRedirect(route('login'));
    $this->post(route('organizer.events.registrations.bulk', [$package['org']->slug, $package['event']->slug]), ['ids' => [$registration->id], 'action' => 'accepted'])->assertRedirect(route('login'));
    $this->get(route('admin.registrations.index'))->assertRedirect(route('login'));
    $this->get(route('admin.registrations.show', $registration->id))->assertRedirect(route('login'));
});

it('owner membaca daftar dan detail seleksi', function (): void {
    $package = regAuthzPackage();
    $registration = regAuthzRegistration($package);

    $this->actingAs($package['owner'])
        ->get(route('organizer.events.registrations.index', [$package['org']->slug, $package['event']->slug]))
        ->assertOk();
    $this->actingAs($package['owner'])
        ->get(route('organizer.events.registrations.show', [$package['org']->slug, $package['event']->slug, $registration->id]))
        ->assertOk();
});

it('owner memutuskan seleksi dan bulk', function (): void {
    $package = regAuthzPackage();
    $first = regAuthzRegistration($package);
    $second = regAuthzRegistration($package);

    $this->actingAs($package['owner'])->withSession(regAuthzConfirm())
        ->post(route('organizer.events.registrations.review', [$package['org']->slug, $package['event']->slug, $first->id]), [
            'action' => 'accepted',
        ])
        ->assertRedirect();
    $this->actingAs($package['owner'])->withSession(regAuthzConfirm())
        ->post(route('organizer.events.registrations.bulk', [$package['org']->slug, $package['event']->slug]), [
            'ids' => [$second->id],
            'action' => 'waitlisted',
        ])
        ->assertRedirect();

    expect($first->refresh()->status)->toBe('accepted')
        ->and($second->refresh()->status)->toBe('waitlisted');
});

it('staf ber-perm membaca dan memutuskan seleksi', function (): void {
    $package = regAuthzPackage();
    $staff = regAuthzStaff($package, 'staf-berperm@reg-authz.test');
    $staff->givePermissionTo('registration.read', 'registration.review');
    $registration = regAuthzRegistration($package);

    $this->actingAs($staff->refresh())
        ->get(route('organizer.events.registrations.index', [$package['org']->slug, $package['event']->slug]))
        ->assertOk();
    $this->actingAs($staff->refresh())
        ->get(route('organizer.events.registrations.show', [$package['org']->slug, $package['event']->slug, $registration->id]))
        ->assertOk();
    $this->actingAs($staff->refresh())->withSession(regAuthzConfirm())
        ->post(route('organizer.events.registrations.review', [$package['org']->slug, $package['event']->slug, $registration->id]), [
            'action' => 'waitlisted',
        ])
        ->assertRedirect();

    expect($registration->refresh()->status)->toBe('waitlisted');
});

it('staf tanpa perm ditolak 403 pada area seleksi', function (): void {
    $package = regAuthzPackage();
    $staff = regAuthzStaff($package, 'staf-tanpa-perm@reg-authz.test');
    $registration = regAuthzRegistration($package);

    $this->actingAs($staff)
        ->get(route('organizer.events.registrations.index', [$package['org']->slug, $package['event']->slug]))
        ->assertForbidden();
    $this->actingAs($staff)
        ->get(route('organizer.events.registrations.show', [$package['org']->slug, $package['event']->slug, $registration->id]))
        ->assertForbidden();
    $this->actingAs($staff)->withSession(regAuthzConfirm())
        ->post(route('organizer.events.registrations.review', [$package['org']->slug, $package['event']->slug, $registration->id]), [
            'action' => 'waitlisted',
        ])
        ->assertForbidden();
    $this->actingAs($staff)->withSession(regAuthzConfirm())
        ->post(route('organizer.events.registrations.bulk', [$package['org']->slug, $package['event']->slug]), [
            'ids' => [$registration->id],
            'action' => 'waitlisted',
        ])
        ->assertForbidden();

    expect($registration->refresh()->status)->toBe('under_review');
});

it('staf org lain mendapat 404 pada area seleksi', function (): void {
    $package = regAuthzPackage();
    $other = regAuthzPackage();
    $staffOther = regAuthzStaff($other, 'staf-org-lain@reg-authz.test');
    $registration = regAuthzRegistration($package);

    $this->actingAs($staffOther)
        ->get(route('organizer.events.registrations.index', [$package['org']->slug, $package['event']->slug]))
        ->assertNotFound();
    $this->actingAs($staffOther)
        ->get(route('organizer.events.registrations.show', [$package['org']->slug, $package['event']->slug, $registration->id]))
        ->assertNotFound();
});

it('volunteer membuka milik sendiri tetapi 404 milik orang lain', function (): void {
    $package = regAuthzPackage();
    $volunteer = User::factory()->create();
    VolunteerProfile::factory()->create(['user_id' => $volunteer->id]);
    $otherVolunteer = User::factory()->create();
    VolunteerProfile::factory()->create(['user_id' => $otherVolunteer->id]);
    $mine = regAuthzRegistration($package, $volunteer, 'pending');
    $theirs = regAuthzRegistration($package, $otherVolunteer, 'pending');

    $this->actingAs($volunteer)->get(route('registrations.index'))->assertOk();
    $this->actingAs($volunteer)->get(route('registrations.show', $mine->id))->assertOk();
    $this->actingAs($volunteer)->get(route('registrations.show', $theirs->id))->assertNotFound();
    $this->actingAs($volunteer)->post(route('registrations.withdraw', $mine->id))->assertRedirect();
    $this->actingAs($volunteer)->post(route('registrations.withdraw', $theirs->id))->assertNotFound();

    expect($mine->refresh()->status)->toBe('withdrawn')
        ->and($theirs->refresh()->status)->toBe('pending');
});

it('non-admin mendapat 403 pada panel pendaftaran admin', function (): void {
    $package = regAuthzPackage();
    $registration = regAuthzRegistration($package);
    $volunteer = User::factory()->create();
    $staff = regAuthzStaff($package, 'staf-non-admin@reg-authz.test');

    $this->actingAs($volunteer)->get(route('admin.registrations.index'))->assertForbidden();
    $this->actingAs($volunteer)->get(route('admin.registrations.show', $registration->id))->assertForbidden();
    $this->actingAs($staff)->get(route('admin.registrations.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('admin.registrations.show', $registration->id))->assertForbidden();
    $this->actingAs($package['owner'])->get(route('admin.registrations.index'))->assertForbidden();
    $this->actingAs($package['owner'])->get(route('admin.registrations.show', $registration->id))->assertForbidden();
});

it('review saat kuota penuh menolak dengan rollback status dan counter utuh', function (): void {
    $package = regAuthzPackage();
    $package['role']->forceFill(['quota' => 1])->save();
    $first = regAuthzRegistration($package);

    $this->actingAs($package['owner'])->withSession(regAuthzConfirm())
        ->post(route('organizer.events.registrations.review', [$package['org']->slug, $package['event']->slug, $first->id]), [
            'action' => 'accepted',
        ])
        ->assertRedirect();

    $second = regAuthzRegistration($package);

    $this->actingAs($package['owner'])->withSession(regAuthzConfirm())
        ->from(route('organizer.events.registrations.show', [$package['org']->slug, $package['event']->slug, $second->id]))
        ->post(route('organizer.events.registrations.review', [$package['org']->slug, $package['event']->slug, $second->id]), [
            'action' => 'accepted',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('action');

    expect($first->refresh()->status)->toBe('accepted')
        ->and($second->refresh()->status)->toBe('under_review')
        ->and($package['role']->refresh()->accepted_count)->toBe(1);
});

it('bulk gagal di tengah membatalkan seluruh perubahan dan counter', function (): void {
    $package = regAuthzPackage();
    $okFirst = regAuthzRegistration($package);
    $failedMiddle = regAuthzRegistration($package, null, 'pending');
    $okLast = regAuthzRegistration($package);

    $this->actingAs($package['owner'])->withSession(regAuthzConfirm())
        ->from(route('organizer.events.registrations.index', [$package['org']->slug, $package['event']->slug]))
        ->post(route('organizer.events.registrations.bulk', [$package['org']->slug, $package['event']->slug]), [
            'ids' => [$okFirst->id, $failedMiddle->id, $okLast->id],
            'action' => 'accepted',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('ids');

    expect($okFirst->refresh()->status)->toBe('under_review')
        ->and($failedMiddle->refresh()->status)->toBe('pending')
        ->and($okLast->refresh()->status)->toBe('under_review')
        ->and($package['role']->refresh()->accepted_count)->toBe(0);
});

it('volunteer mengelola profilnya sendiri', function (): void {
    $volunteer = User::factory()->create();

    $this->actingAs($volunteer)->get(route('profile.volunteer.edit'))->assertOk();
    $this->actingAs($volunteer)->patch(route('profile.volunteer.update'), [
        'full_name' => 'Relawan Authz',
        'visibility' => 'organizers_only',
    ])->assertRedirect(route('profile.volunteer.edit'));

    $this->assertDatabaseHas('volunteer_profiles', [
        'user_id' => $volunteer->id,
        'full_name' => 'Relawan Authz',
    ]);
});

it('custom field milik event lain 404 saat diakses lintas scope', function (): void {
    $package = regAuthzPackage();
    $other = regAuthzPackage();
    $foreign = EventCustomField::factory()->create(['event_id' => $other['event']->id]);

    $this->actingAs($package['owner'])
        ->get(route('organizer.events.fields.show', [$package['org']->slug, $package['event']->slug, $foreign->id]))
        ->assertNotFound();
    $this->actingAs($other['owner'])
        ->get(route('organizer.events.fields.show', [$other['org']->slug, $other['event']->slug, $foreign->id]))
        ->assertOk();
});

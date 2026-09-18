<?php

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\RegistrationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event, role: EventRole} */
function seleksiTesSetup(int $kuota = 5): array
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
        'quota' => $kuota,
        'accepted_count' => 0,
    ]);
    EventCustomField::factory()->create(['event_id' => $event->id, 'type' => 'text']);

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'role' => $role->refresh(),
    ];
}

function seleksiTesDaftar(array $setup, ?User $volunteer = null, string $status = 'under_review'): Registration
{
    $relawan = $volunteer ?? User::factory()->create();
    $reg = Registration::unguarded(fn (): Registration => Registration::create([
        'user_id' => $relawan->id,
        'event_id' => $setup['event']->id,
        'role_id' => $setup['role']->id,
        'status' => 'pending',
        'submitted_at' => now(),
        'idempotency_key' => (string) Str::uuid(),
    ]));
    if ($status !== 'pending') {
        app(RegistrationService::class)->review($reg->refresh(), $status, $setup['owner']);
    }

    return $reg->refresh();
}

/** @return array<string, mixed> */
function seleksiTesKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('tamu diarahkan ke login saat membuka area seleksi', function (): void {
    $setup = seleksiTesSetup();
    $reg = seleksiTesDaftar($setup);

    $this->get(route('organizer.events.registrations.index', [$setup['org']->slug, $setup['event']->slug]))
        ->assertRedirect(route('login'));
    $this->get(route('organizer.events.registrations.show', [$setup['org']->slug, $setup['event']->slug, $reg->id]))
        ->assertRedirect(route('login'));
});

it('daftar seleksi mendukung filter status dan paginasi', function (): void {
    $setup = seleksiTesSetup();
    $pending = seleksiTesDaftar($setup, null, 'pending');
    $review = seleksiTesDaftar($setup);
    foreach (range(1, 14) as $i) {
        seleksiTesDaftar($setup, null, 'pending');
    }

    $response = $this->actingAs($setup['owner'])
        ->get(route('organizer.events.registrations.index', [$setup['org']->slug, $setup['event']->slug, 'status' => 'under_review']));

    $response->assertOk()
        ->assertSee($review->user->name)
        ->assertDontSee($pending->user->name);

    $page = $this->actingAs($setup['owner'])
        ->get(route('organizer.events.registrations.index', [$setup['org']->slug, $setup['event']->slug]));

    $page->assertOk();
    expect($page->viewData('registrations')->perPage())->toBe(15)
        ->and($page->viewData('registrations')->total())->toBe(16);
});

it('review under_review ke accepted menaikkan counter kuota', function (): void {
    $setup = seleksiTesSetup();
    $reg = seleksiTesDaftar($setup);

    $this->actingAs($setup['owner'])->withSession(seleksiTesKonfirmasi())
        ->post(route('organizer.events.registrations.review', [$setup['org']->slug, $setup['event']->slug, $reg->id]), [
            'action' => 'accepted',
        ])
        ->assertRedirect(route('organizer.events.registrations.show', [$setup['org']->slug, $setup['event']->slug, $reg->id]));

    expect($reg->refresh()->status)->toBe('accepted')
        ->and($setup['role']->refresh()->accepted_count)->toBe(1);
});

it('review ke rejected wajib menyertakan alasan', function (): void {
    $setup = seleksiTesSetup();
    $reg = seleksiTesDaftar($setup);

    $this->actingAs($setup['owner'])->withSession(seleksiTesKonfirmasi())
        ->postJson(route('organizer.events.registrations.review', [$setup['org']->slug, $setup['event']->slug, $reg->id]), [
            'action' => 'rejected',
        ])
        ->assertUnprocessable();

    expect($reg->refresh()->status)->toBe('under_review');

    $this->actingAs($setup['owner'])->withSession(seleksiTesKonfirmasi())
        ->post(route('organizer.events.registrations.review', [$setup['org']->slug, $setup['event']->slug, $reg->id]), [
            'action' => 'rejected',
            'reason' => 'Berkas kurang lengkap.',
        ])
        ->assertRedirect();

    expect($reg->refresh()->status)->toBe('rejected')
        ->and($reg->refresh()->rejection_reason)->toBe('Berkas kurang lengkap.');
});

it('review ke waitlisted berhasil', function (): void {
    $setup = seleksiTesSetup();
    $reg = seleksiTesDaftar($setup);

    $this->actingAs($setup['owner'])->withSession(seleksiTesKonfirmasi())
        ->post(route('organizer.events.registrations.review', [$setup['org']->slug, $setup['event']->slug, $reg->id]), [
            'action' => 'waitlisted',
        ])
        ->assertRedirect();

    expect($reg->refresh()->status)->toBe('waitlisted');
});

it('accept saat kuota penuh mengembalikan 422', function (): void {
    $setup = seleksiTesSetup(1);
    $pertama = seleksiTesDaftar($setup);
    $this->actingAs($setup['owner'])->withSession(seleksiTesKonfirmasi())
        ->post(route('organizer.events.registrations.review', [$setup['org']->slug, $setup['event']->slug, $pertama->id]), [
            'action' => 'accepted',
        ])
        ->assertRedirect();
    $kedua = seleksiTesDaftar($setup);

    $this->actingAs($setup['owner'])->withSession(seleksiTesKonfirmasi())
        ->from(route('organizer.events.registrations.show', [$setup['org']->slug, $setup['event']->slug, $kedua->id]))
        ->post(route('organizer.events.registrations.review', [$setup['org']->slug, $setup['event']->slug, $kedua->id]), [
            'action' => 'accepted',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('action');

    expect($kedua->refresh()->status)->toBe('under_review')
        ->and($setup['role']->refresh()->accepted_count)->toBe(1);
});

it('bulk accept tiga pendaftaran sekaligus', function (): void {
    $setup = seleksiTesSetup();
    $ids = [];
    foreach (range(1, 3) as $i) {
        $ids[] = seleksiTesDaftar($setup)->id;
    }

    $this->actingAs($setup['owner'])->withSession(seleksiTesKonfirmasi())
        ->post(route('organizer.events.registrations.bulk', [$setup['org']->slug, $setup['event']->slug]), [
            'ids' => $ids,
            'action' => 'accepted',
        ])
        ->assertRedirect(route('organizer.events.registrations.index', [$setup['org']->slug, $setup['event']->slug]));

    expect(Registration::whereIn('id', $ids)->where('status', 'accepted')->count())->toBe(3)
        ->and($setup['role']->refresh()->accepted_count)->toBe(3);
});

it('bulk dengan satu id lintas event gagal 404 dan rollback', function (): void {
    $setup = seleksiTesSetup();
    $reg = seleksiTesDaftar($setup);
    $lain = seleksiTesSetup();
    $asing = seleksiTesDaftar($lain);

    $this->actingAs($setup['owner'])->withSession(seleksiTesKonfirmasi())
        ->postJson(route('organizer.events.registrations.bulk', [$setup['org']->slug, $setup['event']->slug]), [
            'ids' => [$reg->id, $asing->id],
            'action' => 'accepted',
        ])
        ->assertNotFound();

    expect($reg->refresh()->status)->toBe('under_review')
        ->and($asing->refresh()->status)->toBe('under_review')
        ->and($setup['role']->refresh()->accepted_count)->toBe(0);
});

it('staff read-only tidak boleh bulk walau boleh melihat daftar', function (): void {
    $setup = seleksiTesSetup();
    $staf = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $setup['org']->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staf->refresh(), $setup['org']);
    $staf->refresh()->givePermissionTo('registration.read');
    $reg = seleksiTesDaftar($setup);

    expect($staf->refresh()->can('registration.read'))->toBeTrue()
        ->and($staf->refresh()->can('registration.review'))->toBeFalse();

    $this->actingAs($staf->refresh())
        ->get(route('organizer.events.registrations.index', [$setup['org']->slug, $setup['event']->slug]))
        ->assertOk();

    $this->actingAs($staf->refresh())->withSession(seleksiTesKonfirmasi())
        ->post(route('organizer.events.registrations.bulk', [$setup['org']->slug, $setup['event']->slug]), [
            'ids' => [$reg->id],
            'action' => 'accepted',
        ])
        ->assertForbidden();

    expect($reg->refresh()->status)->toBe('under_review');
});

it('staff tanpa permission registration.review mendapat 403', function (): void {
    $setup = seleksiTesSetup();
    $staf = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $setup['org']->id,
        'user_id' => $staf->id,
        'role' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($staf->refresh(), $setup['org']);
    $reg = seleksiTesDaftar($setup);

    $this->actingAs($staf->refresh())
        ->get(route('organizer.events.registrations.index', [$setup['org']->slug, $setup['event']->slug]))
        ->assertForbidden();
    $this->actingAs($staf->refresh())->withSession(seleksiTesKonfirmasi())
        ->post(route('organizer.events.registrations.review', [$setup['org']->slug, $setup['event']->slug, $reg->id]), [
            'action' => 'accepted',
        ])
        ->assertForbidden();
});

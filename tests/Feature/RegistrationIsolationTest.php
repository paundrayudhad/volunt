<?php

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Registration;
use App\Models\RegistrationAnswer;
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

/** @return array{org: Organization, owner: User, event: Event, role: EventRole, field: EventCustomField} */
function regIsolasiPaket(string $slug): array
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

    $event = Event::factory()->create(['organization_id' => $org->id, 'slug' => $slug]);
    $event->forceFill(['status' => 'registration_open'])->save();
    $division = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'quota' => 5,
        'accepted_count' => 0,
    ]);
    $field = EventCustomField::factory()->create(['event_id' => $event->id, 'type' => 'text']);

    return [
        'org' => $org->refresh(),
        'owner' => $owner->refresh(),
        'event' => $event->refresh(),
        'role' => $role->refresh(),
        'field' => $field->refresh(),
    ];
}

/** @return array{paketA: array<string, mixed>, paketB: array<string, mixed>, regA: Registration, regB: Registration} */
function regIsolasiPasangan(): array
{
    $paketA = regIsolasiPaket('festival-isolasi-a');
    $paketB = regIsolasiPaket('festival-isolasi-b');
    $regA = regIsolasiDaftar($paketA);
    $regB = regIsolasiDaftar($paketB);

    return ['paketA' => $paketA, 'paketB' => $paketB, 'regA' => $regA, 'regB' => $regB];
}

function regIsolasiDaftar(array $paket, ?User $volunteer = null, string $status = 'under_review'): Registration
{
    $member = $volunteer ?? User::factory()->create();
    $reg = Registration::unguarded(fn (): Registration => Registration::create([
        'user_id' => $member->id,
        'event_id' => $paket['event']->id,
        'role_id' => $paket['role']->id,
        'status' => $status,
        'submitted_at' => now(),
        'idempotency_key' => (string) Str::uuid(),
    ]));
    RegistrationAnswer::unguarded(fn (): mixed => $reg->answers()->create([
        'event_custom_field_id' => $paket['field']->id,
        'value_text' => 'Jawaban rahasia '.$paket['event']->slug.'.',
    ]));

    return $reg->refresh();
}

/** @return array<string, int> */
function regIsolasiKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

it('read silang pendaftaran menghasilkan 404 kedua arah', function (): void {
    $pair = regIsolasiPasangan();
    [$paketA, $paketB, $regA, $regB] = [$pair['paketA'], $pair['paketB'], $pair['regA'], $pair['regB']];

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.registrations.show', [$paketA['org']->slug, $paketA['event']->slug, $regB->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.registrations.show', [$paketB['org']->slug, $paketB['event']->slug, $regA->id]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.registrations.index', [$paketB['org']->slug, $paketB['event']->slug]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.registrations.index', [$paketA['org']->slug, $paketA['event']->slug]))
        ->assertNotFound();
});

it('review dan bulk silang menghasilkan 404 kedua arah dan data utuh', function (): void {
    $pair = regIsolasiPasangan();
    [$paketA, $paketB, $regA, $regB] = [$pair['paketA'], $pair['paketB'], $pair['regA'], $pair['regB']];

    $this->actingAs($paketA['owner'])->withSession(regIsolasiKonfirmasi())
        ->post(route('organizer.events.registrations.review', [$paketA['org']->slug, $paketA['event']->slug, $regB->id]), [
            'action' => 'waitlisted',
        ])
        ->assertNotFound();
    $this->actingAs($paketB['owner'])->withSession(regIsolasiKonfirmasi())
        ->post(route('organizer.events.registrations.review', [$paketB['org']->slug, $paketB['event']->slug, $regA->id]), [
            'action' => 'waitlisted',
        ])
        ->assertNotFound();
    $this->actingAs($paketA['owner'])->withSession(regIsolasiKonfirmasi())
        ->postJson(route('organizer.events.registrations.bulk', [$paketA['org']->slug, $paketA['event']->slug]), [
            'ids' => [$regA->id, $regB->id],
            'action' => 'waitlisted',
        ])
        ->assertNotFound();
    $this->actingAs($paketB['owner'])->withSession(regIsolasiKonfirmasi())
        ->postJson(route('organizer.events.registrations.bulk', [$paketB['org']->slug, $paketB['event']->slug]), [
            'ids' => [$regB->id, $regA->id],
            'action' => 'waitlisted',
        ])
        ->assertNotFound();

    expect($regA->refresh()->status)->toBe('under_review')
        ->and($regB->refresh()->status)->toBe('under_review');
    $this->assertDatabaseHas('registrations', ['id' => $regA->id, 'event_id' => $paketA['event']->id]);
    $this->assertDatabaseHas('registrations', ['id' => $regB->id, 'event_id' => $paketB['event']->id]);
});

it('field dan jawaban silang menghasilkan 404 kedua arah', function (): void {
    $pair = regIsolasiPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.fields.show', [$paketA['org']->slug, $paketA['event']->slug, $paketB['field']->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.fields.show', [$paketB['org']->slug, $paketB['event']->slug, $paketA['field']->id]))
        ->assertNotFound();
    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.fields.edit', [$paketA['org']->slug, $paketA['event']->slug, $paketB['field']->id]))
        ->assertNotFound();
    $this->actingAs($paketB['owner'])
        ->get(route('organizer.events.fields.edit', [$paketB['org']->slug, $paketB['event']->slug, $paketA['field']->id]))
        ->assertNotFound();
});

it('katalog publik tidak membocorkan jawaban pendaftaran', function (): void {
    $pair = regIsolasiPasangan();
    [$paketA, $paketB] = [$pair['paketA'], $pair['paketB']];
    $paketA['event']->forceFill(['status' => 'published', 'published_at' => now()])->save();
    $paketB['event']->forceFill(['status' => 'published', 'published_at' => now()])->save();

    $index = $this->get(route('events.index'));
    $index->assertOk()
        ->assertSee('festival-isolasi-a')
        ->assertDontSee('Jawaban rahasia');

    $show = $this->get(route('events.show', $paketA['event']->slug));
    $show->assertOk()
        ->assertDontSee('Jawaban rahasia');
});

it('daftar seleksi org A tidak menampilkan pendaftaran org B', function (): void {
    $pair = regIsolasiPasangan();
    [$paketA, $regA, $regB] = [$pair['paketA'], $pair['regA'], $pair['regB']];

    $this->actingAs($paketA['owner'])
        ->get(route('organizer.events.registrations.index', [$paketA['org']->slug, $paketA['event']->slug]))
        ->assertOk()
        ->assertSee($regA->user->name)
        ->assertDontSee($regB->user->name);
});

it('volunteer luar mendapat 404 pada area seleksi kedua org', function (): void {
    $pair = regIsolasiPasangan();
    [$paketA, $paketB, $regA, $regB] = [$pair['paketA'], $pair['paketB'], $pair['regA'], $pair['regB']];
    $volunteer = User::factory()->create();
    VolunteerProfile::factory()->create(['user_id' => $volunteer->id]);

    foreach ([$paketA, $paketB] as $paket) {
        $this->actingAs($volunteer)
            ->get(route('organizer.events.registrations.index', [$paket['org']->slug, $paket['event']->slug]))
            ->assertNotFound();
    }
    $this->actingAs($volunteer)
        ->get(route('organizer.events.registrations.show', [$paketA['org']->slug, $paketA['event']->slug, $regA->id]))
        ->assertNotFound();
    $this->actingAs($volunteer)
        ->get(route('organizer.events.registrations.show', [$paketB['org']->slug, $paketB['event']->slug, $regB->id]))
        ->assertNotFound();

    expect($regA->refresh()->status)->toBe('under_review')
        ->and($regB->refresh()->status)->toBe('under_review');
});

<?php

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Support\Str;

/** @return array{org: Organization, volunteer: User, event: Event, role: EventRole, field: EventCustomField} */
function daftarTesPaket(string $eventStatus = 'registration_open'): array
{
    $org = Organization::factory()->create(['status' => 'active']);
    $volunteer = User::factory()->create();
    VolunteerProfile::factory()->create(['user_id' => $volunteer->id]);
    $event = Event::factory()->create(['organization_id' => $org->id]);
    $event->forceFill(['status' => $eventStatus])->save();
    $division = EventDivision::factory()->create(['event_id' => $event->id]);
    $role = EventRole::factory()->create([
        'event_id' => $event->id,
        'division_id' => $division->id,
        'quota' => 5,
        'accepted_count' => 0,
    ]);
    $field = EventCustomField::factory()->create([
        'event_id' => $event->id,
        'type' => 'text',
        'required' => false,
    ]);

    return [
        'org' => $org->refresh(),
        'volunteer' => $volunteer->refresh(),
        'event' => $event->refresh(),
        'role' => $role->refresh(),
        'field' => $field->refresh(),
    ];
}

/** @return array<string, mixed> */
function daftarTesPayload(array $setup, array $overrides = []): array
{
    return array_merge([
        'role_id' => $setup['role']->id,
        'idempotency_key' => (string) Str::uuid(),
        'answers' => [$setup['field']->id => 'Saya siap membantu.'],
    ], $overrides);
}

it('tamu diarahkan ke login saat membuka area registrasi', function (): void {
    $setup = daftarTesPaket();

    $this->get(route('registrations.index'))->assertRedirect(route('login'));
    $this->get(route('registrations.create', $setup['event']->slug))->assertRedirect(route('login'));
    $this->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertRedirect(route('login'));
});

it('submit dengan role dan jawaban valid membuat registrasi pending', function (): void {
    $setup = daftarTesPaket();

    $res = $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup));

    $reg = Registration::firstOrFail();
    $res->assertRedirect(route('registrations.show', $reg->id));
    expect($reg->status)->toBe('pending')
        ->and($reg->user_id)->toBe($setup['volunteer']->id)
        ->and($reg->event_id)->toBe($setup['event']->id)
        ->and($reg->role_id)->toBe($setup['role']->id)
        ->and($reg->answers()->count())->toBe(1)
        ->and($reg->answers()->first()->value_text)->toBe('Saya siap membantu.')
        ->and($reg->histories()->count())->toBe(1)
        ->and($reg->histories()->first()->to_status)->toBe('pending');
});

it('submit tanpa profil volunteer ditolak 422', function (): void {
    $setup = daftarTesPaket();
    $userWithoutProfile = User::factory()->create();

    $this->actingAs($userWithoutProfile)
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Lengkapi profil volunteer dulu.');

    expect(Registration::count())->toBe(0);
});

it('jawaban required yang kosong ditolak 422', function (): void {
    $setup = daftarTesPaket();
    $setup['field']->update(['required' => true]);

    $this->actingAs($setup['volunteer'])
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup, [
            'answers' => [$setup['field']->id => ''],
        ]))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0);
});

it('jawaban field di luar event ditolak 422', function (): void {
    $setup = daftarTesPaket();
    $foreignEvent = Event::factory()->create();
    $foreignField = EventCustomField::factory()->create(['event_id' => $foreignEvent->id, 'type' => 'text']);

    $this->actingAs($setup['volunteer'])
        ->postJson(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup, [
            'answers' => [$setup['field']->id => 'Saya siap.', $foreignField->id => 'Jawaban asing.'],
        ]))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0);
});

it('double-submit dengan idempotency key sama hanya menyimpan satu record', function (): void {
    $setup = daftarTesPaket();
    $payload = daftarTesPayload($setup);

    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), $payload)
        ->assertRedirect();
    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), $payload)
        ->assertRedirect();

    expect(Registration::where('idempotency_key', $payload['idempotency_key'])->count())->toBe(1)
        ->and(Registration::count())->toBe(1);
});

it('withdraw milik sendiri berhasil dan milik orang lain 404', function (): void {
    $setup = daftarTesPaket();
    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertRedirect();
    $reg = Registration::firstOrFail();

    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.withdraw', $reg->id))
        ->assertRedirect(route('registrations.show', $reg->id));

    expect($reg->refresh()->status)->toBe('withdrawn');

    $otherUser = User::factory()->create();
    VolunteerProfile::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($otherUser)
        ->post(route('registrations.withdraw', $reg->id))
        ->assertNotFound();
});

it('event yang belum membuka registrasi ditolak 422', function (): void {
    $setup = daftarTesPaket('published');

    $this->actingAs($setup['volunteer'])
        ->post(route('registrations.store', $setup['event']->slug), daftarTesPayload($setup))
        ->assertUnprocessable();

    expect(Registration::count())->toBe(0);
});

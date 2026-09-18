<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\EventService;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array{org: Organization, owner: User, event: Event} */
function fieldTesPaket(string $slug = 'festival-field'): array
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
    $event = app(EventService::class)->createEvent($org->refresh(), [
        'name' => 'Festival Field',
        'slug' => $slug,
        'start_at' => now()->addMonth(),
        'end_at' => now()->addMonth()->addDays(2),
    ], $owner->refresh());

    return ['org' => $org->refresh(), 'owner' => $owner->refresh(), 'event' => $event->refresh()];
}

function fieldTesStaf(Organization $org, User $owner, string $email): User
{
    $staf = User::factory()->create(['email' => $email]);
    $undangan = app(MembershipService::class)->invite($org, ['email' => $email], $owner);
    app(MembershipService::class)->acceptInvitation($undangan, $staf);

    return $staf->refresh();
}

/** @return array<string, mixed> */
function fieldTesPayloadTeks(): array
{
    return [
        'label' => 'Nomor HP Darurat',
        'type' => 'text',
        'required' => true,
        'placeholder' => '08xxxxxxxxxx',
        'sort_order' => 1,
        'is_active' => true,
    ];
}

/** @return array<string, mixed> */
function fieldTesPayloadPilihan(): array
{
    return [
        'label' => 'Ukuran Kaos',
        'type' => 'select',
        'required' => true,
        'sort_order' => 2,
        'is_active' => true,
        'options' => [
            ['label' => 'S', 'value' => 'S'],
            ['label' => 'M', 'value' => 'M'],
        ],
    ];
}

it('membuat field teks wajib dan field pilihan dengan opsi', function (): void {
    $paket = fieldTesPaket();
    [$org, $owner, $event] = [$paket['org'], $paket['owner'], $paket['event']];

    $this->actingAs($owner)
        ->post(route('organizer.events.fields.store', [$org->slug, $event->slug]), fieldTesPayloadTeks())
        ->assertRedirect();
    $this->assertDatabaseHas('event_custom_fields', [
        'event_id' => $event->id,
        'label' => 'Nomor HP Darurat',
        'type' => 'text',
    ]);

    $this->actingAs($owner)
        ->post(route('organizer.events.fields.store', [$org->slug, $event->slug]), fieldTesPayloadPilihan())
        ->assertRedirect();
    $field = $event->customFields()->where('label', 'Ukuran Kaos')->firstOrFail();
    expect($field->options()->count())->toBe(2);
    $this->assertDatabaseHas('event_custom_field_options', [
        'event_custom_field_id' => $field->id,
        'label' => 'S',
        'value' => 'S',
    ]);

    $this->actingAs($owner)
        ->get(route('organizer.events.fields.index', [$org->slug, $event->slug]))
        ->assertOk()
        ->assertSee('Nomor HP Darurat')
        ->assertSee('Ukuran Kaos');
});

it('tipe tidak valid ditolak 422', function (): void {
    $paket = fieldTesPaket();
    [$org, $owner, $event] = [$paket['org'], $paket['owner'], $paket['event']];

    $this->actingAs($owner)
        ->postJson(route('organizer.events.fields.store', [$org->slug, $event->slug]), [
            ...fieldTesPayloadTeks(),
            'label' => 'Field Tipe Aneh',
            'type' => 'pilihan_ganda',
        ])
        ->assertUnprocessable();

    $this->assertDatabaseMissing('event_custom_fields', [
        'event_id' => $event->id,
        'label' => 'Field Tipe Aneh',
    ]);
});

it('opsi pada tipe teks ditolak karena hanya empat tipe opsi', function (): void {
    $paket = fieldTesPaket();
    [$org, $owner, $event] = [$paket['org'], $paket['owner'], $paket['event']];

    $this->actingAs($owner)
        ->postJson(route('organizer.events.fields.store', [$org->slug, $event->slug]), [
            ...fieldTesPayloadTeks(),
            'label' => 'Teks Beropsi',
            'options' => [
                ['label' => 'A', 'value' => 'a'],
            ],
        ])
        ->assertUnprocessable();

    $this->assertDatabaseMissing('event_custom_fields', [
        'event_id' => $event->id,
        'label' => 'Teks Beropsi',
    ]);
});

it('memperbarui field dan menonaktifkan', function (): void {
    $paket = fieldTesPaket();
    [$org, $owner, $event] = [$paket['org'], $paket['owner'], $paket['event']];

    $this->actingAs($owner)
        ->post(route('organizer.events.fields.store', [$org->slug, $event->slug]), fieldTesPayloadPilihan())
        ->assertRedirect();
    $field = $event->customFields()->where('label', 'Ukuran Kaos')->firstOrFail();

    $this->actingAs($owner)
        ->get(route('organizer.events.fields.edit', [$org->slug, $event->slug, $field->id]))
        ->assertOk();

    $this->actingAs($owner)
        ->patch(route('organizer.events.fields.update', [$org->slug, $event->slug, $field->id]), [
            ...fieldTesPayloadPilihan(),
            'label' => 'Ukuran Kaos Diperbarui',
            'is_active' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('event_custom_fields', [
        'id' => $field->id,
        'label' => 'Ukuran Kaos Diperbarui',
        'is_active' => false,
    ]);
    expect($field->fresh()?->options()->count())->toBe(2);
});

it('menghapus field ikut menghapus opsi', function (): void {
    $paket = fieldTesPaket();
    [$org, $owner, $event] = [$paket['org'], $paket['owner'], $paket['event']];

    $this->actingAs($owner)
        ->post(route('organizer.events.fields.store', [$org->slug, $event->slug]), fieldTesPayloadPilihan())
        ->assertRedirect();
    $field = $event->customFields()->where('label', 'Ukuran Kaos')->firstOrFail();
    $this->assertDatabaseHas('event_custom_field_options', ['event_custom_field_id' => $field->id]);

    $this->actingAs($owner)
        ->delete(route('organizer.events.fields.destroy', [$org->slug, $event->slug, $field->id]))
        ->assertRedirect();

    $this->assertDatabaseMissing('event_custom_fields', ['id' => $field->id]);
    $this->assertDatabaseMissing('event_custom_field_options', ['event_custom_field_id' => $field->id]);
});

it('staf tanpa permission ditolak 403', function (): void {
    $paket = fieldTesPaket();
    [$org, $owner, $event] = [$paket['org'], $paket['owner'], $paket['event']];
    $staf = fieldTesStaf($org, $owner, 'staf-tanpa-perm@field.test');

    $this->actingAs($staf)
        ->get(route('organizer.events.fields.index', [$org->slug, $event->slug]))
        ->assertForbidden();
    $this->actingAs($staf)
        ->post(route('organizer.events.fields.store', [$org->slug, $event->slug]), fieldTesPayloadTeks())
        ->assertForbidden();
    $this->assertDatabaseMissing('event_custom_fields', [
        'event_id' => $event->id,
        'label' => 'Nomor HP Darurat',
    ]);
});

it('field lintas event menghasilkan 404', function (): void {
    $paket = fieldTesPaket();
    [$org, $owner, $event] = [$paket['org'], $paket['owner'], $paket['event']];
    $eventLain = app(EventService::class)->createEvent($org, [
        'name' => 'Festival Lain',
        'slug' => 'festival-field-lain',
        'start_at' => now()->addMonth(),
        'end_at' => now()->addMonth()->addDays(2),
    ], $owner);

    $this->actingAs($owner)
        ->post(route('organizer.events.fields.store', [$org->slug, $eventLain->slug]), fieldTesPayloadTeks())
        ->assertRedirect();
    $field = $eventLain->customFields()->where('label', 'Nomor HP Darurat')->firstOrFail();

    $this->actingAs($owner)
        ->get(route('organizer.events.fields.show', [$org->slug, $event->slug, $field->id]))
        ->assertNotFound();
    $this->actingAs($owner)
        ->patch(route('organizer.events.fields.update', [$org->slug, $event->slug, $field->id]), fieldTesPayloadTeks())
        ->assertNotFound();
    $this->actingAs($owner)
        ->delete(route('organizer.events.fields.destroy', [$org->slug, $event->slug, $field->id]))
        ->assertNotFound();

    $this->assertDatabaseHas('event_custom_fields', ['id' => $field->id]);
});

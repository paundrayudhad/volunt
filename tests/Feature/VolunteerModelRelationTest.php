<?php

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventCustomFieldOption;
use App\Models\EventRole;
use App\Models\Registration;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('profil relawan milik user dan user memiliki satu profil', function (): void {
    $user = User::factory()->create();
    $profil = VolunteerProfile::factory()->for($user)->create();

    expect($profil->user->is($user))->toBeTrue()
        ->and($user->refresh()->volunteerProfile->is($profil))->toBeTrue()
        ->and($profil->visibility)->toBe('organizers_only');
});

it('registrasi terhubung ke user event dan role serta menolak duplikat aktif', function (): void {
    $role = EventRole::factory()->create();
    $user = User::factory()->create();

    $pendaftaran = DB::transaction(fn () => Registration::factory()->for($user)->create([
        'event_id' => $role->event_id,
        'role_id' => $role->id,
    ]));

    expect($pendaftaran->user->is($user))->toBeTrue()
        ->and($pendaftaran->event->is(Event::find($role->event_id)))->toBeTrue()
        ->and($pendaftaran->role->is($role))->toBeTrue()
        ->and($pendaftaran->isActive())->toBeTrue();

    expect(fn () => DB::transaction(fn () => Registration::factory()->for($user)->create([
        'event_id' => $role->event_id,
        'role_id' => $role->id,
    ])))->toThrow(QueryException::class);
});

it('custom field memiliki options dan menolak tipe di luar 13 tipe valid', function (): void {
    expect(EventCustomField::TYPES)->toHaveCount(13);

    $field = EventCustomField::factory()->create();
    foreach (['Ya', 'Tidak'] as $i => $label) {
        EventCustomFieldOption::unguarded(fn () => EventCustomFieldOption::create([
            'event_custom_field_id' => $field->id,
            'label' => $label,
            'value' => Str::slug($label),
            'sort_order' => $i,
        ]));
    }

    expect($field->refresh()->options)->toHaveCount(2)
        ->and($field->event)->toBeInstanceOf(Event::class);

    expect(fn () => DB::transaction(fn () => EventCustomField::factory()->create(['type' => 'bogus'])))
        ->toThrow(QueryException::class);
});

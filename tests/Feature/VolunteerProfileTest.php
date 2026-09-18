<?php

use App\Models\User;
use App\Models\VolunteerProfile;

it('tamu diarahkan ke login saat membuka profil volunteer', function (): void {
    $this->get(route('profile.volunteer.edit'))->assertRedirect(route('login'));
});

it('volunteer dapat mengisi profilnya sendiri', function (): void {
    $user = User::factory()->create();

    $res = $this->actingAs($user)->patch(route('profile.volunteer.update'), [
        'full_name' => 'Relawan Teladan',
        'phone' => '081234567890',
        'city' => 'Bandung',
        'visibility' => 'organizers_only',
    ]);

    $res->assertRedirect(route('profile.volunteer.edit'));
    $this->assertDatabaseHas('volunteer_profiles', [
        'user_id' => $user->id,
        'full_name' => 'Relawan Teladan',
        'phone' => '081234567890',
        'city' => 'Bandung',
    ]);
});

it('visibilitas profil hanya menerima tiga nilai yang sah', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson(route('profile.volunteer.update'), [
        'full_name' => 'Relawan Teladan',
        'visibility' => 'semua_orang',
    ])->assertUnprocessable();

    expect(VolunteerProfile::where('user_id', $user->id)->count())->toBe(0);
});

it('tanggal lahir tidak boleh di masa depan', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patchJson(route('profile.volunteer.update'), [
        'full_name' => 'Relawan Teladan',
        'visibility' => 'private',
        'date_of_birth' => now()->addDay()->toDateString(),
    ])->assertUnprocessable();

    expect(VolunteerProfile::where('user_id', $user->id)->count())->toBe(0);
});

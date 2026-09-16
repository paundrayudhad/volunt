<?php

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function orgTesDenganOwner(): array
{
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    OrganizationMember::unguarded(fn () => OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]));
    app(MembershipService::class)->syncPermissions($owner->refresh(), $org);

    return [$org, $owner];
}

it('menyimpan pengajuan organisasi sebagai pending', function () {
    $user = User::factory()->create();

    $respon = $this->actingAs($user)->post(route('organizations.requests.store'), [
        'name' => 'Komunitas Relawan',
        'slug' => 'komunitas-relawan',
        'description' => 'Komunitas sosial.',
    ]);

    $respon->assertRedirect(route('organizations.requests.index'));
    $this->assertDatabaseHas('organization_requests', [
        'user_id' => $user->id,
        'slug' => 'komunitas-relawan',
        'status' => 'pending',
    ]);
});

it('menampilkan formulir dan riwayat pengajuan milik sendiri', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('organizations.requests.create'))
        ->assertOk()
        ->assertSee('Pengajuan Organisasi');

    $this->actingAs($user)->post(route('organizations.requests.store'), [
        'name' => 'Yayasan Maju',
        'slug' => 'yayasan-maju',
    ]);

    $this->actingAs($user)
        ->get(route('organizations.requests.index'))
        ->assertOk()
        ->assertSee('Yayasan Maju');
});

it('membatasi pengajuan maksimal 3 kali per menit', function () {
    $user = User::factory()->create();

    for ($i = 1; $i <= 3; $i++) {
        $this->actingAs($user)->post(route('organizations.requests.store'), [
            'name' => "Org $i",
            'slug' => "org-$i",
        ])->assertRedirect();
    }

    $this->actingAs($user)->post(route('organizations.requests.store'), [
        'name' => 'Org 4',
        'slug' => 'org-4',
    ])->assertStatus(429);
});

it('owner dapat memperbarui profil organisasi', function () {
    [$org, $owner] = orgTesDenganOwner();

    $respon = $this->actingAs($owner)->patch(route('organizer.update', $org->slug), [
        'name' => 'Nama Baru',
        'description' => 'Deskripsi baru.',
    ]);

    $respon->assertRedirect(route('organizer.show', $org->slug));
    $this->assertDatabaseHas('organizations', ['id' => $org->id, 'name' => 'Nama Baru']);
});

it('staff tanpa permission organization.update mendapat 403', function () {
    [$org, $owner] = orgTesDenganOwner();

    $staf = User::factory()->create(['email' => 'staf@example.com']);
    $inv = app(MembershipService::class)->invite($org, ['email' => 'staf@example.com'], $owner);
    app(MembershipService::class)->acceptInvitation($inv, $staf);

    $this->actingAs($staf->refresh())->patch(route('organizer.update', $org->slug), [
        'name' => 'Coba Ubah',
    ])->assertForbidden();
});

it('member organisasi lain mendapat 404 pada slug di luar scope', function () {
    [$orgA] = orgTesDenganOwner();
    [, $ownerB] = orgTesDenganOwner();

    $this->actingAs($ownerB)->get(route('organizer.show', $orgA->slug))->assertNotFound();
    $this->actingAs($ownerB)->get(route('organizer.members.index', $orgA->slug))->assertNotFound();
});

it('tamu diarahkan ke halaman login', function () {
    [$org] = orgTesDenganOwner();

    $this->get(route('organizer.show', $org->slug))->assertRedirect('/login');
});

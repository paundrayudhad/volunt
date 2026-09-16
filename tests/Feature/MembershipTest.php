<?php

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function memberTesDenganOwner(): array
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

function terimaStaf(Organization $org, User $owner, string $email): User
{
    $staf = User::factory()->create(['email' => $email]);
    $inv = app(MembershipService::class)->invite($org, ['email' => $email], $owner);
    app(MembershipService::class)->acceptInvitation($inv, $staf);

    return $staf->refresh();
}

it('owner mengundang lalu calon menerima undangan via HTTP', function () {
    [$org, $owner] = memberTesDenganOwner();

    $this->actingAs($owner)->post(route('organizer.members.store', $org->slug), [
        'email' => 'calon@example.com',
    ])->assertRedirect(route('organizer.members.index', $org->slug));

    $this->assertDatabaseHas('organization_invitations', [
        'organization_id' => $org->id,
        'email' => 'calon@example.com',
    ]);

    $calon = User::factory()->create(['email' => 'calon@example.com']);
    $undangan = OrganizationInvitation::where('organization_id', $org->id)
        ->where('email', 'calon@example.com')->firstOrFail();

    $this->actingAs($calon)->post(route('invitations.accept', $undangan->id))
        ->assertRedirect(route('invitations.index'));

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $org->id,
        'user_id' => $calon->id,
        'role' => 'staff',
        'status' => 'active',
    ]);
    expect($undangan->refresh()->accepted_at)->not->toBeNull();
});

it('undangan ulang pada email dengan undangan kedaluwarsa menampilkan pesan kesalahan', function () {
    [$org, $owner] = memberTesDenganOwner();

    $inv = app(MembershipService::class)->invite($org, ['email' => 'basi@example.com'], $owner);
    $inv->forceFill(['expires_at' => now()->subDay()])->save();

    $this->actingAs($owner)->post(route('organizer.members.store', $org->slug), [
        'email' => 'basi@example.com',
    ])->assertRedirect()->assertSessionHasErrors('email');
});

it('owner mengubah role staff via HTTP', function () {
    [$org, $owner] = memberTesDenganOwner();
    $staf = terimaStaf($org, $owner, 'naik@example.com');
    $member = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $staf->id)->firstOrFail();

    $this->actingAs($owner)->patch(
        route('organizer.members.update', [$org->slug, $member->id]),
        ['role' => 'owner']
    )->assertRedirect(route('organizer.members.index', $org->slug));

    expect($member->refresh()->role)->toBe('owner')
        ->and($staf->refresh()->hasPermissionTo('organization.suspend'))->toBeTrue();
});

it('owner mengeluarkan member staff via HTTP', function () {
    [$org, $owner] = memberTesDenganOwner();
    $staf = terimaStaf($org, $owner, 'keluar@example.com');
    $member = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $staf->id)->firstOrFail();

    $this->actingAs($owner)->delete(
        route('organizer.members.destroy', [$org->slug, $member->id])
    )->assertRedirect(route('organizer.members.index', $org->slug));

    $this->assertDatabaseMissing('organization_members', ['id' => $member->id]);
});

it('tidak boleh mengubah role diri sendiri', function () {
    [$org, $owner] = memberTesDenganOwner();
    $milikSendiri = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $owner->id)->firstOrFail();

    $this->actingAs($owner)->patch(
        route('organizer.members.update', [$org->slug, $milikSendiri->id]),
        ['role' => 'staff']
    )->assertForbidden();
});

it('staff tanpa permission member.invite mendapat 403 saat mengundang', function () {
    [$org, $owner] = memberTesDenganOwner();
    $staf = terimaStaf($org, $owner, 'biasa@example.com');

    $this->actingAs($staf)->post(route('organizer.members.store', $org->slug), [
        'email' => 'baru@example.com',
    ])->assertForbidden();
});

it('akses member silang organisasi menghasilkan 404', function () {
    [$orgA, $ownerA] = memberTesDenganOwner();
    [, $ownerB] = memberTesDenganOwner();
    $stafA = terimaStaf($orgA, $ownerA, 'stafa@example.com');
    $memberA = OrganizationMember::where('organization_id', $orgA->id)
        ->where('user_id', $stafA->id)->firstOrFail();

    $this->actingAs($ownerB)->get(route('organizer.members.index', $orgA->slug))->assertNotFound();
    $this->actingAs($ownerB)->patch(
        route('organizer.members.update', [$orgA->slug, $memberA->id]),
        ['role' => 'owner']
    )->assertNotFound();
    $this->actingAs($ownerB)->delete(
        route('organizer.members.destroy', [$orgA->slug, $memberA->id])
    )->assertNotFound();
});

it('id member organisasi lain di bawah slug sendiri menghasilkan 404', function () {
    [$orgA, $ownerA] = memberTesDenganOwner();
    [$orgB] = memberTesDenganOwner();
    $stafA = terimaStaf($orgA, $ownerA, 'lintas@example.com');
    $memberA = OrganizationMember::where('organization_id', $orgA->id)
        ->where('user_id', $stafA->id)->firstOrFail();

    $this->actingAs($ownerA)->patch(
        route('organizer.members.update', [$orgB->slug, $memberA->id]),
        ['role' => 'owner']
    )->assertNotFound();
});

it('pemilik email dapat menolak undangan via HTTP', function () {
    [$org, $owner] = memberTesDenganOwner();

    $calon = User::factory()->create(['email' => 'tolak@example.com']);
    $inv = app(MembershipService::class)->invite($org, ['email' => 'tolak@example.com'], $owner);

    $this->actingAs($calon)->post(route('invitations.decline', $inv->id))
        ->assertRedirect(route('invitations.index'));

    expect($inv->refresh()->declined_at)->not->toBeNull();
});

it('pengguna melihat daftar undangannya sendiri', function () {
    [$org, $owner] = memberTesDenganOwner();
    app(MembershipService::class)->invite($org, ['email' => 'saya@example.com'], $owner);

    $saya = User::factory()->create(['email' => 'saya@example.com']);

    $this->actingAs($saya)->get(route('invitations.index'))
        ->assertOk()
        ->assertSee($org->name);
});

it('transfer kepemilikan wajib konfirmasi password', function () {
    [$org, $owner] = memberTesDenganOwner();
    $staf = terimaStaf($org, $owner, 'penerus@example.com');
    $memberStaf = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $staf->id)->firstOrFail();

    $this->actingAs($owner)->post(route('organizer.transfer', $org->slug), [
        'member_id' => $memberStaf->id,
    ])->assertRedirectToRoute('password.confirm');

    $denganKonfirmasi = $this->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('organizer.transfer', $org->slug), [
            'member_id' => $memberStaf->id,
        ]);
    $denganKonfirmasi->assertRedirect(route('organizer.show', $org->slug));

    expect($owner->refresh()->organizationRole($org->id))->toBe('staff')
        ->and($staf->refresh()->organizationRole($org->id))->toBe('owner');
});

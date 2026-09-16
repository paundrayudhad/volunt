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

function isolasiBuatOrgDenganOwner(): array
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

function isolasiTerimaStaf(Organization $org, User $owner, string $email): User
{
    $staf = User::factory()->create(['email' => $email]);
    $inv = app(MembershipService::class)->invite($org, ['email' => $email], $owner);
    app(MembershipService::class)->acceptInvitation($inv, $staf);

    return $staf->refresh();
}

function isolasiPasanganOrg(): array
{
    [$orgA, $ownerA] = isolasiBuatOrgDenganOwner();
    [$orgB, $ownerB] = isolasiBuatOrgDenganOwner();
    $stafA = isolasiTerimaStaf($orgA, $ownerA, 'staf-a@isolasi.test');
    $stafB = isolasiTerimaStaf($orgB, $ownerB, 'staf-b@isolasi.test');

    return [$orgA, $ownerA, $stafA, $orgB, $ownerB, $stafB];
}

it('A tidak bisa read atau update organisasi B, dan sebaliknya', function () {
    [$orgA, $ownerA, $stafA, $orgB, $ownerB, $stafB] = isolasiPasanganOrg();

    foreach ([$ownerA, $stafA] as $aktorA) {
        $this->actingAs($aktorA)->get(route('organizer.show', $orgB->slug))->assertNotFound();
        $this->actingAs($aktorA)->get(route('organizer.edit', $orgB->slug))->assertNotFound();
        $this->actingAs($aktorA)->patch(route('organizer.update', $orgB->slug), [
            'name' => 'Coba Ubah Silang',
        ])->assertNotFound();
    }

    foreach ([$ownerB, $stafB] as $aktorB) {
        $this->actingAs($aktorB)->get(route('organizer.show', $orgA->slug))->assertNotFound();
        $this->actingAs($aktorB)->get(route('organizer.edit', $orgA->slug))->assertNotFound();
        $this->actingAs($aktorB)->patch(route('organizer.update', $orgA->slug), [
            'name' => 'Coba Ubah Silang',
        ])->assertNotFound();
    }

    expect($orgA->refresh()->name)->not->toBe('Coba Ubah Silang')
        ->and($orgB->refresh()->name)->not->toBe('Coba Ubah Silang');
});

it('A tidak bisa melihat member B, dan sebaliknya', function () {
    [$orgA, $ownerA, $stafA, $orgB, $ownerB, $stafB] = isolasiPasanganOrg();

    foreach ([$ownerA, $stafA] as $aktorA) {
        $this->actingAs($aktorA)->get(route('organizer.members.index', $orgB->slug))->assertNotFound();
        $this->actingAs($aktorA)->get(route('organizer.members.create', $orgB->slug))->assertNotFound();
    }

    foreach ([$ownerB, $stafB] as $aktorB) {
        $this->actingAs($aktorB)->get(route('organizer.members.index', $orgA->slug))->assertNotFound();
        $this->actingAs($aktorB)->get(route('organizer.members.create', $orgA->slug))->assertNotFound();
    }
});

it('A tidak bisa mengundang atas nama B, dan sebaliknya', function () {
    [$orgA, $ownerA, , $orgB, $ownerB] = isolasiPasanganOrg();

    $this->actingAs($ownerA)->post(route('organizer.members.store', $orgB->slug), [
        'email' => 'silang-a-ke-b@isolasi.test',
    ])->assertNotFound();

    $this->actingAs($ownerB)->post(route('organizer.members.store', $orgA->slug), [
        'email' => 'silang-b-ke-a@isolasi.test',
    ])->assertNotFound();

    $this->assertDatabaseMissing('organization_invitations', ['email' => 'silang-a-ke-b@isolasi.test']);
    $this->assertDatabaseMissing('organization_invitations', ['email' => 'silang-b-ke-a@isolasi.test']);
});

it('A tidak bisa mengubah atau menghapus member B, dan sebaliknya', function () {
    [$orgA, $ownerA, , $orgB, $ownerB] = isolasiPasanganOrg();
    $stafA = isolasiTerimaStaf($orgA, $ownerA, 'staf-a-ubah@isolasi.test');
    $stafB = isolasiTerimaStaf($orgB, $ownerB, 'staf-b-ubah@isolasi.test');
    $memberA = OrganizationMember::where('organization_id', $orgA->id)
        ->where('user_id', $stafA->id)->firstOrFail();
    $memberB = OrganizationMember::where('organization_id', $orgB->id)
        ->where('user_id', $stafB->id)->firstOrFail();

    $this->actingAs($ownerA)->patch(
        route('organizer.members.update', [$orgB->slug, $memberB->id]),
        ['role' => 'owner']
    )->assertNotFound();
    $this->actingAs($ownerA)->delete(
        route('organizer.members.destroy', [$orgB->slug, $memberB->id])
    )->assertNotFound();

    $this->actingAs($ownerB)->patch(
        route('organizer.members.update', [$orgA->slug, $memberA->id]),
        ['role' => 'owner']
    )->assertNotFound();
    $this->actingAs($ownerB)->delete(
        route('organizer.members.destroy', [$orgA->slug, $memberA->id])
    )->assertNotFound();

    expect($memberA->refresh()->role)->toBe('staff')
        ->and($memberB->refresh()->role)->toBe('staff');
    $this->assertDatabaseHas('organization_members', ['id' => $memberA->id]);
    $this->assertDatabaseHas('organization_members', ['id' => $memberB->id]);
});

it('A tidak bisa menerima undangan B, dan sebaliknya', function () {
    [$orgA, $ownerA, $stafA, $orgB, $ownerB, $stafB] = isolasiPasanganOrg();

    $invB = app(MembershipService::class)->invite($orgB, ['email' => 'calon-b@isolasi.test'], $ownerB);
    $calonB = User::factory()->create(['email' => 'calon-b@isolasi.test']);

    // Member A (email beda) mencoba accept undangan B → 403.
    $this->actingAs($ownerA)->post(route('invitations.accept', $invB->id))->assertForbidden();
    $this->actingAs($stafA)->post(route('invitations.accept', $invB->id))->assertForbidden();
    $this->actingAs($ownerA)->post(route('invitations.decline', $invB->id))->assertForbidden();

    // Pemilik sah tetap bisa menerima.
    $this->actingAs($calonB)->post(route('invitations.accept', $invB->id))
        ->assertRedirect(route('invitations.index'));
    expect($calonB->refresh()->belongsToOrganization($orgB->id))->toBeTrue()
        ->and($calonB->belongsToOrganization($orgA->id))->toBeFalse();

    $invA = app(MembershipService::class)->invite($orgA, ['email' => 'calon-a@isolasi.test'], $ownerA);

    $this->actingAs($ownerB)->post(route('invitations.accept', $invA->id))->assertForbidden();
    $this->actingAs($stafB)->post(route('invitations.decline', $invA->id))->assertForbidden();
});

it('volunteer luar ditolak pada resource kedua organisasi', function () {
    [$orgA, , , $orgB] = isolasiPasanganOrg();
    $volunteer = User::factory()->create();

    $this->actingAs($volunteer)->get(route('organizer.show', $orgA->slug))->assertNotFound();
    $this->actingAs($volunteer)->get(route('organizer.show', $orgB->slug))->assertNotFound();
    $this->actingAs($volunteer)->get(route('organizer.members.index', $orgA->slug))->assertNotFound();
    $this->actingAs($volunteer)->get(route('organizer.members.index', $orgB->slug))->assertNotFound();
    $this->actingAs($volunteer)->post(route('organizer.members.store', $orgA->slug), [
        'email' => 'coba@isolasi.test',
    ])->assertNotFound();
    $this->actingAs($volunteer)->patch(route('organizer.update', $orgB->slug), [
        'name' => 'Coba Ubah',
    ])->assertNotFound();
});

it('id member silang slug menghasilkan 404 kedua arah', function () {
    [$orgA, $ownerA, , $orgB, $ownerB] = isolasiPasanganOrg();
    $stafA = isolasiTerimaStaf($orgA, $ownerA, 'staf-a-silang@isolasi.test');
    $stafB = isolasiTerimaStaf($orgB, $ownerB, 'staf-b-silang@isolasi.test');
    $memberA = OrganizationMember::where('organization_id', $orgA->id)
        ->where('user_id', $stafA->id)->firstOrFail();
    $memberB = OrganizationMember::where('organization_id', $orgB->id)
        ->where('user_id', $stafB->id)->firstOrFail();

    // Owner sah memakai slug sendiri tetapi id member milik org lain → 404.
    $this->actingAs($ownerA)->patch(
        route('organizer.members.update', [$orgA->slug, $memberB->id]),
        ['role' => 'owner']
    )->assertNotFound();
    $this->actingAs($ownerB)->patch(
        route('organizer.members.update', [$orgB->slug, $memberA->id]),
        ['role' => 'owner']
    )->assertNotFound();
    $this->actingAs($ownerA)->delete(
        route('organizer.members.destroy', [$orgA->slug, $memberB->id])
    )->assertNotFound();
});

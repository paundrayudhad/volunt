<?php

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\OrganizationRequest;
use App\Models\User;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function authzBuatOrgDenganOwner(): array
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

function authzTerimaStaf(Organization $org, User $owner, string $email): User
{
    $staf = User::factory()->create(['email' => $email]);
    $inv = app(MembershipService::class)->invite($org, ['email' => $email], $owner);
    app(MembershipService::class)->acceptInvitation($inv, $staf);

    return $staf->refresh();
}

function authzSuperAdmin(): User
{
    return tap(User::factory()->create(), fn (User $u) => $u->assignRole('super_admin'))->refresh();
}

function authzPengajuan(User $pemohon, string $nama): OrganizationRequest
{
    return OrganizationRequest::unguarded(fn () => OrganizationRequest::create([
        'user_id' => $pemohon->id,
        'name' => $nama,
        'slug' => Str::slug($nama).'-'.Str::lower(Str::random(4)),
        'description' => 'Deskripsi '.$nama,
        'status' => 'pending',
    ]));
}

// ——— Tamu (sesi segar, tanpa actingAs sebelumnya) ———

it('tamu diarahkan ke login pada semua endpoint terproteksi', function () {
    [$org] = authzBuatOrgDenganOwner();
    $undangan = OrganizationInvitation::first()
        ?? app(MembershipService::class)->invite($org, ['email' => 'calon@tamu.test'], User::factory()->create());
    $req = authzPengajuan(User::factory()->create(), 'Pengajuan Tamu');

    $this->get(route('organizer.show', $org->slug))->assertRedirect('/login');
    $this->get(route('organizer.members.index', $org->slug))->assertRedirect('/login');
    $this->get(route('invitations.index'))->assertRedirect('/login');
    $this->post(route('invitations.accept', $undangan->id))->assertRedirect('/login');
    $this->get(route('admin.requests.index'))->assertRedirect(route('login'));
    $this->post(route('admin.requests.approve', $req->id))->assertRedirect(route('login'));
});

it('tamu melihat halaman utama beserta header X-Request-ID', function () {
    $this->get('/')->assertOk()->assertHeader('X-Request-ID');
});

// ——— Owner ———

it('owner mengakses dashboard org, member list, form undang, dan edit profil', function () {
    [$org, $owner] = authzBuatOrgDenganOwner();

    $this->actingAs($owner)->get(route('organizer.show', $org->slug))->assertOk();
    $this->actingAs($owner)->get(route('organizer.edit', $org->slug))->assertOk();
    $this->actingAs($owner)->get(route('organizer.members.index', $org->slug))->assertOk();
    $this->actingAs($owner)->get(route('organizer.members.create', $org->slug))->assertOk();
    $this->actingAs($owner)->patch(route('organizer.update', $org->slug), [
        'name' => 'Nama Diperbarui Owner',
    ])->assertRedirect(route('organizer.show', $org->slug));
    $this->actingAs($owner)->post(route('organizer.members.store', $org->slug), [
        'email' => 'staf-baru@authz.test',
    ])->assertRedirect(route('organizer.members.index', $org->slug));
});

it('owner mengelola role dan menghapus member staff', function () {
    [$org, $owner] = authzBuatOrgDenganOwner();
    $staf = authzTerimaStaf($org, $owner, 'staf-kelola@authz.test');
    $member = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $staf->id)->firstOrFail();

    $this->actingAs($owner)->patch(
        route('organizer.members.update', [$org->slug, $member->id]),
        ['role' => 'owner']
    )->assertRedirect(route('organizer.members.index', $org->slug));

    $korban = authzTerimaStaf($org, $owner, 'staf-hapus@authz.test');
    $memberKorban = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $korban->id)->firstOrFail();

    $this->actingAs($owner)->delete(
        route('organizer.members.destroy', [$org->slug, $memberKorban->id])
    )->assertRedirect(route('organizer.members.index', $org->slug));
});

// ——— Staff dengan permission tambahan ———

it('staff dengan permission tambahan lolos endpoint terproteksi permission', function () {
    [$org, $owner] = authzBuatOrgDenganOwner();
    $staf = authzTerimaStaf($org, $owner, 'staf-berperm@authz.test');
    app(MembershipService::class)->grantPermission($owner, $staf, $org, 'member.invite');
    app(MembershipService::class)->grantPermission($owner, $staf, $org, 'organization.update');

    $this->actingAs($staf->refresh())->get(route('organizer.show', $org->slug))->assertOk();
    $this->actingAs($staf->refresh())->get(route('organizer.members.index', $org->slug))->assertOk();
    $this->actingAs($staf->refresh())->get(route('organizer.members.create', $org->slug))->assertOk();
    $this->actingAs($staf->refresh())->post(route('organizer.members.store', $org->slug), [
        'email' => 'undangan-dari-staf@authz.test',
    ])->assertRedirect(route('organizer.members.index', $org->slug));
    $this->actingAs($staf->refresh())->patch(route('organizer.update', $org->slug), [
        'name' => 'Nama Diperbarui Staf',
    ])->assertRedirect(route('organizer.show', $org->slug));
});

// ——— Staff tanpa permission ———

it('staff tanpa permission ditolak 403 pada operasi terproteksi', function () {
    [$org, $owner] = authzBuatOrgDenganOwner();
    $staf = authzTerimaStaf($org, $owner, 'staf-tanpa-perm@authz.test');

    // Dashboard dan member list boleh (STAFF_BASE), sisanya ditolak.
    $this->actingAs($staf)->get(route('organizer.show', $org->slug))->assertOk();
    $this->actingAs($staf)->get(route('organizer.members.index', $org->slug))->assertOk();

    $this->actingAs($staf)->get(route('organizer.edit', $org->slug))->assertForbidden();
    $this->actingAs($staf)->patch(route('organizer.update', $org->slug), [
        'name' => 'Coba Ubah',
    ])->assertForbidden();
    $this->actingAs($staf)->get(route('organizer.members.create', $org->slug))->assertForbidden();
    $this->actingAs($staf)->post(route('organizer.members.store', $org->slug), [
        'email' => 'gagal@authz.test',
    ])->assertForbidden();
});

it('staff tanpa permission member.change_role ditolak saat mengubah role', function () {
    [$org, $owner] = authzBuatOrgDenganOwner();
    $staf = authzTerimaStaf($org, $owner, 'staf-ubah-role@authz.test');
    $korban = authzTerimaStaf($org, $owner, 'staf-korban@authz.test');
    $memberKorban = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $korban->id)->firstOrFail();

    $this->actingAs($staf)->patch(
        route('organizer.members.update', [$org->slug, $memberKorban->id]),
        ['role' => 'owner']
    )->assertForbidden();
});

it('staff tanpa permission member.remove ditolak saat menghapus member', function () {
    [$org, $owner] = authzBuatOrgDenganOwner();
    $staf = authzTerimaStaf($org, $owner, 'staf-hapus-coba@authz.test');
    $korban = authzTerimaStaf($org, $owner, 'staf-korban-hapus@authz.test');
    $memberKorban = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $korban->id)->firstOrFail();

    $this->actingAs($staf)->delete(
        route('organizer.members.destroy', [$org->slug, $memberKorban->id])
    )->assertForbidden();
});

// ——— Staff organisasi lain → 404 (scoped binding) ———

it('staff organisasi lain mendapat 404 pada resource di luar scope', function () {
    [$orgA, $ownerA] = authzBuatOrgDenganOwner();
    [$orgB, $ownerB] = authzBuatOrgDenganOwner();
    $stafB = authzTerimaStaf($orgB, $ownerB, 'staf-org-b@authz.test');
    $memberA = OrganizationMember::where('organization_id', $orgA->id)
        ->where('user_id', $ownerA->id)->firstOrFail();

    $this->actingAs($stafB)->get(route('organizer.show', $orgA->slug))->assertNotFound();
    $this->actingAs($stafB)->get(route('organizer.edit', $orgA->slug))->assertNotFound();
    $this->actingAs($stafB)->get(route('organizer.members.index', $orgA->slug))->assertNotFound();
    $this->actingAs($stafB)->post(route('organizer.members.store', $orgA->slug), [
        'email' => 'silang@authz.test',
    ])->assertNotFound();
    $this->actingAs($stafB)->patch(
        route('organizer.members.update', [$orgA->slug, $memberA->id]),
        ['role' => 'staff']
    )->assertNotFound();
});

// ——— Volunteer (user tanpa membership) ———

it('volunteer tanpa membership ditolak pada endpoint organizer dan undangan orang lain', function () {
    [$org, $owner] = authzBuatOrgDenganOwner();
    $volunteer = User::factory()->create();
    $undangan = app(MembershipService::class)->invite($org, ['email' => 'calon-lain@authz.test'], $owner);

    // Route binding scoped oleh membership: volunteer mendapat 404 pada slug org.
    $this->actingAs($volunteer)->get(route('organizer.show', $org->slug))->assertNotFound();
    $this->actingAs($volunteer)->get(route('organizer.members.index', $org->slug))->assertNotFound();
    $this->actingAs($volunteer)->patch(route('organizer.update', $org->slug), [
        'name' => 'Coba Ubah Volunteer',
    ])->assertNotFound();

    // Undangan milik email lain → policy menolak (403).
    $this->actingAs($volunteer)->post(route('invitations.accept', $undangan->id))->assertForbidden();
    $this->actingAs($volunteer)->post(route('invitations.decline', $undangan->id))->assertForbidden();
});

it('volunteer menerima undangan yang ditujukan ke emailnya', function () {
    [$org, $owner] = authzBuatOrgDenganOwner();
    $calon = User::factory()->create(['email' => 'calon-volunteer@authz.test']);
    $undangan = app(MembershipService::class)->invite($org, ['email' => 'calon-volunteer@authz.test'], $owner);

    $this->actingAs($calon)->post(route('invitations.accept', $undangan->id))
        ->assertRedirect(route('invitations.index'));

    expect($calon->refresh()->belongsToOrganization($org->id))->toBeTrue();
});

it('volunteer melihat riwayat pengajuan organisasinya sendiri', function () {
    $volunteer = User::factory()->create();

    $this->actingAs($volunteer)->get(route('organizations.requests.index'))->assertOk();
    $this->actingAs($volunteer)->get(route('organizations.requests.create'))->assertOk();
    $this->actingAs($volunteer)->post(route('organizations.requests.store'), [
        'name' => 'Komunitas Volunteer',
        'slug' => 'komunitas-volunteer',
    ])->assertRedirect(route('organizations.requests.index'));
});

// ——— Non-admin ke /admin ———

it('non-admin mendapat 403 pada panel admin', function () {
    [$org, $owner] = authzBuatOrgDenganOwner();
    $volunteer = User::factory()->create();
    $staf = authzTerimaStaf($org, $owner, 'staf-non-admin@authz.test');
    $req = authzPengajuan($volunteer, 'Pengajuan Non Admin');

    foreach ([$volunteer, $owner, $staf] as $aktor) {
        $this->actingAs($aktor)->get(route('admin.requests.index'))->assertForbidden();
        $this->actingAs($aktor)->get(route('admin.organizations.index'))->assertForbidden();
        $this->actingAs($aktor)->get(route('admin.logs.index'))->assertForbidden();
        $this->actingAs($aktor)->post(route('admin.requests.approve', $req->id))->assertForbidden();
        $this->actingAs($aktor)->post(route('admin.requests.reject', $req->id), [
            'reason' => 'Alasan.',
        ])->assertForbidden();
    }
});

it('super_admin mengakses seluruh panel admin', function () {
    $admin = authzSuperAdmin();
    [$org] = authzBuatOrgDenganOwner();
    $req = authzPengajuan(User::factory()->create(), 'Pengajuan Untuk Admin');

    $this->actingAs($admin)->get(route('admin.requests.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.organizations.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.logs.index'))->assertOk();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.requests.approve', $req->id))
        ->assertRedirect(route('admin.requests.index'));
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.organizations.suspend', $org->id), ['reason' => 'Pelanggaran.'])
        ->assertRedirect(route('admin.organizations.index'));
});

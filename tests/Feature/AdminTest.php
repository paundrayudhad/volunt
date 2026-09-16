<?php

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationRequest;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\SecurityService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function adminTesSuperAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return $admin->refresh();
}

function adminTesPengajuan(User $pemohon, string $nama, string $status = 'pending'): OrganizationRequest
{
    return OrganizationRequest::unguarded(fn () => OrganizationRequest::create([
        'user_id' => $pemohon->id,
        'name' => $nama,
        'slug' => Str::slug($nama).'-'.Str::lower(Str::random(4)),
        'description' => 'Deskripsi '.$nama,
        'status' => $status,
    ]));
}

function adminTesKonfirmasi(): array
{
    return ['auth.password_confirmed_at' => time()];
}

function adminTesOwnerOrganisasi(): array
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

it('super_admin melihat antrean dengan pengajuan pending paling atas', function () {
    $admin = adminTesSuperAdmin();
    $pemohon = User::factory()->create();
    adminTesPengajuan($pemohon, 'Pengajuan Lama', 'rejected');
    adminTesPengajuan($pemohon, 'Pending A');
    adminTesPengajuan($pemohon, 'Pending B');

    $this->actingAs($admin)->get(route('admin.requests.index'))
        ->assertOk()
        ->assertSee('Antrean Pengajuan')
        ->assertSeeInOrder(['Pending B', 'Pending A', 'Pengajuan Lama']);
});

it('super_admin menyetujui pengajuan menjadi organisasi aktif dengan owner', function () {
    $admin = adminTesSuperAdmin();
    $pemohon = User::factory()->create();
    $req = adminTesPengajuan($pemohon, 'Komunitas Disetujui');

    $respon = $this->actingAs($admin)->withSession(adminTesKonfirmasi())
        ->post(route('admin.requests.approve', $req->id));

    $respon->assertRedirect(route('admin.requests.index'));

    $org = Organization::where('slug', $req->slug)->firstOrFail();
    expect($org->status)->toBe('active')
        ->and($pemohon->refresh()->organizationRole($org->id))->toBe('owner')
        ->and($pemohon->can('organization.update'))->toBeTrue()
        ->and($req->refresh()->status)->toBe('approved');

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'organization.approved',
    ]);
});

it('reject tanpa alasan mengembalikan 422', function () {
    $admin = adminTesSuperAdmin();
    $req = adminTesPengajuan(User::factory()->create(), 'Komunitas Ditolak');

    $this->actingAs($admin)->postJson(route('admin.requests.reject', $req->id), [])
        ->assertStatus(422);

    expect($req->refresh()->status)->toBe('pending');
});

it('reject dengan alasan menolak pengajuan dan mencatat audit', function () {
    $admin = adminTesSuperAdmin();
    $req = adminTesPengajuan(User::factory()->create(), 'Komunitas Kurang Data');

    $this->actingAs($admin)
        ->post(route('admin.requests.reject', $req->id), ['reason' => 'Data profil belum lengkap.'])
        ->assertRedirect(route('admin.requests.index'));

    expect($req->refresh()->status)->toBe('rejected')
        ->and($req->refresh()->rejection_reason)->toBe('Data profil belum lengkap.');

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'organization.rejected',
    ]);
});

it('super_admin melihat daftar organisasi', function () {
    $admin = adminTesSuperAdmin();
    $org = Organization::factory()->create(['name' => 'Yayasan Terbuka']);

    $this->actingAs($admin)->get(route('admin.organizations.index'))
        ->assertOk()
        ->assertSee('Kelola Organisasi')
        ->assertSee('Yayasan Terbuka')
        ->assertSee($org->status);
});

it('suspend tanpa alasan mengembalikan 422', function () {
    $admin = adminTesSuperAdmin();
    $org = Organization::factory()->create();

    $this->actingAs($admin)->withSession(adminTesKonfirmasi())
        ->postJson(route('admin.organizations.suspend', $org->id), [])
        ->assertStatus(422);

    expect($org->refresh()->status)->toBe('active');
});

it('suspend dengan alasan menangguhkan organisasi dan mencatat audit', function () {
    $admin = adminTesSuperAdmin();
    $org = Organization::factory()->create();

    $this->actingAs($admin)->withSession(adminTesKonfirmasi())
        ->post(route('admin.organizations.suspend', $org->id), ['reason' => 'Melanggar ketentuan.'])
        ->assertRedirect(route('admin.organizations.index'));

    expect($org->refresh()->status)->toBe('suspended');

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'organization.suspended',
    ]);
});

it('operasi sensitif tanpa konfirmasi password dialihkan ke halaman konfirmasi', function () {
    $admin = adminTesSuperAdmin();
    $req = adminTesPengajuan(User::factory()->create(), 'Komunitas Reauth');
    $org = Organization::factory()->create();

    $this->actingAs($admin)->post(route('admin.requests.approve', $req->id))
        ->assertRedirectToRoute('password.confirm');

    $this->actingAs($admin)->post(route('admin.organizations.suspend', $org->id), ['reason' => 'Alasan.'])
        ->assertRedirectToRoute('password.confirm');

    $this->actingAs($admin)->post(route('admin.organizations.archive', $org->id), ['reason' => 'Alasan.'])
        ->assertRedirectToRoute('password.confirm');

    expect($req->refresh()->status)->toBe('pending')
        ->and($org->refresh()->status)->toBe('active');
});

it('archive dan activate mengubah status dan mencatat audit', function () {
    $admin = adminTesSuperAdmin();
    $org = Organization::factory()->create();

    $this->actingAs($admin)->withSession(adminTesKonfirmasi())
        ->post(route('admin.organizations.archive', $org->id), ['reason' => 'Kegiatan berhenti.'])
        ->assertRedirect(route('admin.organizations.index'));

    expect($org->refresh()->status)->toBe('archived');

    $this->actingAs($admin)->withSession(adminTesKonfirmasi())
        ->post(route('admin.organizations.activate', $org->id))
        ->assertRedirect(route('admin.organizations.index'));

    expect($org->refresh()->status)->toBe('active');

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'organization.archived',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'organization.activated',
    ]);
});

it('non-admin tidak bisa mengakses panel admin', function () {
    // Tamu sejati dicek lebih dulu sebelum actingAs apa pun:
    // middleware auth berjalan duluan sehingga tamu diarahkan ke login.
    $this->get(route('admin.requests.index'))->assertRedirect(route('login'));

    $biasa = User::factory()->create();

    $this->actingAs($biasa)->get(route('admin.requests.index'))->assertForbidden();
    $this->actingAs($biasa)->get(route('admin.organizations.index'))->assertForbidden();
    $this->actingAs($biasa)->get(route('admin.logs.index'))->assertForbidden();

    [, $owner] = adminTesOwnerOrganisasi();

    $this->actingAs($owner)->get(route('admin.requests.index'))->assertForbidden();
    $this->actingAs($owner)->get(route('admin.organizations.index'))->assertForbidden();
});

it('log admin hanya baca dengan filter dan pagination', function () {
    $admin = adminTesSuperAdmin();
    $pemohon = User::factory()->create();
    $req = adminTesPengajuan($pemohon, 'Komunitas Beraudit');

    $this->actingAs($admin)->withSession(adminTesKonfirmasi())
        ->post(route('admin.requests.approve', $req->id))
        ->assertRedirect();

    app(SecurityService::class)->record(null, 'failed_login', ['email' => 'acak@example.com']);

    $this->actingAs($admin)->get(route('admin.logs.index'))
        ->assertOk()
        ->assertSee('Log Audit dan Keamanan')
        ->assertSee('Aksi: organization.approved')
        ->assertSee('baca-saja');

    $this->actingAs($admin)
        ->get(route('admin.logs.index', ['tab' => 'audit', 'action' => 'organization.approved']))
        ->assertOk()
        ->assertSee('Aksi: organization.approved');

    $this->actingAs($admin)
        ->get(route('admin.logs.index', ['tab' => 'audit', 'action' => 'aksi.tidak.ada']))
        ->assertOk()
        ->assertDontSee('Aksi: organization.approved');

    $this->actingAs($admin)
        ->get(route('admin.logs.index', ['tab' => 'keamanan']))
        ->assertOk()
        ->assertSee('failed_login');

    $this->actingAs($admin)
        ->get(route('admin.logs.index', ['tab' => 'keamanan', 'type' => 'failed_login']))
        ->assertOk()
        ->assertSee('failed_login');

    $this->actingAs($admin)->post(route('admin.logs.index'))->assertStatus(405);
    $this->actingAs($admin)->delete(route('admin.logs.index'))->assertStatus(405);
});

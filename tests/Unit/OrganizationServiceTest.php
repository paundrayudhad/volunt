<?php

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('membuat pengajuan organisasi baru berstatus pending', function () {
    $user = User::factory()->create();

    $req = app(OrganizationService::class)->request([
        'name' => 'Komunitas Relawan',
        'slug' => 'komunitas-relawan',
        'description' => 'Komunitas sosial.',
    ], $user);

    expect($req->status)->toBe('pending')
        ->and($req->user_id)->toBe($user->id)
        ->and(AuditLog::where('action', 'organization.requested')->count())->toBe(1);
});

it('menolak pengajuan keempat saat tiga pengajuan pending', function () {
    $user = User::factory()->create();
    $svc = app(OrganizationService::class);

    for ($i = 1; $i <= 3; $i++) {
        $svc->request(['name' => "Org $i", 'slug' => "org-$i"], $user);
    }

    expect(fn () => $svc->request(['name' => 'Org 4', 'slug' => 'org-4'], $user))
        ->toThrow(ValidationException::class);
});

it('menyetujui pengajuan menjadi organisasi aktif dengan pemohon sebagai owner', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $svc = app(OrganizationService::class);

    $req = $svc->request(['name' => 'Yayasan Baik', 'slug' => 'yayasan-baik'], $user);
    $org = $svc->approve($req, $admin);

    expect($org->status)->toBe('active')
        ->and($user->refresh()->organizationRole($org->id))->toBe('owner')
        ->and($user->hasPermissionTo('organization.suspend'))->toBeTrue()
        ->and($user->hasPermissionTo('audit.read'))->toBeTrue()
        ->and($req->refresh()->status)->toBe('approved')
        ->and($req->reviewed_by)->toBe($admin->id);
});

it('menolak persetujuan ganda dengan 422', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $svc = app(OrganizationService::class);

    $req = $svc->request(['name' => 'Yayasan Ganda', 'slug' => 'yayasan-ganda'], $user);
    $svc->approve($req, $admin);

    try {
        $svc->approve($req->refresh(), $admin);
        $this->fail('Seharusnya melempar HttpException 422.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }
});

it('menolak penolakan tanpa alasan dengan 422', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $svc = app(OrganizationService::class);

    $req = $svc->request(['name' => 'Yayasan Tolak', 'slug' => 'yayasan-tolak'], $user);

    expect(fn () => $svc->reject($req, $admin, '   '))->toThrow(HttpException::class);
});

it('menolak pengajuan dengan alasan yang dicatat', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $svc = app(OrganizationService::class);

    $req = $svc->request(['name' => 'Yayasan Batal', 'slug' => 'yayasan-batal'], $user);
    $hasil = $svc->reject($req, $admin, 'Dokumen tidak lengkap.');

    expect($hasil->status)->toBe('rejected')
        ->and($hasil->rejection_reason)->toBe('Dokumen tidak lengkap.')
        ->and($hasil->reviewed_by)->toBe($admin->id);
});

it('menolak suspend tanpa alasan dengan 422', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create();

    expect(fn () => app(OrganizationService::class)->suspend($org, $admin, ''))
        ->toThrow(HttpException::class);
});

it('menangguhkan lalu mengaktifkan kembali organisasi', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create();
    $svc = app(OrganizationService::class);

    $svc->suspend($org, $admin, 'Pelanggaran aturan.');
    expect($org->refresh()->status)->toBe('suspended');

    $svc->activate($org->refresh(), $admin);
    expect($org->refresh()->status)->toBe('active');
});

it('mengarsipkan organisasi dengan alasan', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create();

    $hasil = app(OrganizationService::class)->archive($org, $admin, 'Program selesai.');

    expect($hasil->status)->toBe('archived');
});

it('menolak aktivasi organisasi yang sudah aktif dengan 422', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create();

    expect(fn () => app(OrganizationService::class)->activate($org, $admin))
        ->toThrow(HttpException::class);
});

it('memperbarui profil organisasi dan mencatat audit', function () {
    $org = Organization::factory()->create();
    $actor = User::factory()->create();

    $hasil = app(OrganizationService::class)->updateProfile($org, [
        'name' => 'Nama Baru',
        'description' => 'Deskripsi baru.',
        'status' => 'archived',
    ], $actor);

    expect($hasil->name)->toBe('Nama Baru')
        ->and($hasil->status)->toBe('active')
        ->and(AuditLog::where('action', 'organization.updated')->count())->toBe(1);
});

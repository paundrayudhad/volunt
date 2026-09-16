<?php

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\MembershipService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function buatOrgDenganOwner(): array
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

it('mengundang staff dan menyediakan plain_token', function () {
    [$org, $owner] = buatOrgDenganOwner();

    $inv = app(MembershipService::class)->invite($org, [
        'email' => 'staf@example.com',
        'role' => 'staff',
    ], $owner);

    expect($inv->plain_token)->not->toBeNull()
        ->and(strlen($inv->plain_token))->toBe(40)
        ->and($inv->token_hash)->toBe(hash('sha256', $inv->plain_token))
        ->and($inv->email)->toBe('staf@example.com');
});

it('menolak undangan dengan role owner dengan 422', function () {
    [$org, $owner] = buatOrgDenganOwner();

    expect(fn () => app(MembershipService::class)->invite($org, [
        'email' => 'calon@example.com',
        'role' => 'owner',
    ], $owner))->toThrow(HttpException::class);
});

it('menolak undangan untuk email yang sudah menjadi member dengan 422', function () {
    [$org, $owner] = buatOrgDenganOwner();

    expect(fn () => app(MembershipService::class)->invite($org, [
        'email' => $owner->email,
        'role' => 'staff',
    ], $owner))->toThrow(HttpException::class);
});

it('menolak undangan ganda yang masih pending dengan 422', function () {
    [$org, $owner] = buatOrgDenganOwner();
    $svc = app(MembershipService::class);

    $svc->invite($org, ['email' => 'ganda@example.com', 'role' => 'staff'], $owner);

    expect(fn () => $svc->invite($org, ['email' => 'ganda@example.com', 'role' => 'staff'], $owner))
        ->toThrow(HttpException::class);
});

it('menerima undangan menjadi member staff dengan permission dasar', function () {
    [$org, $owner] = buatOrgDenganOwner();
    $svc = app(MembershipService::class);

    $staf = User::factory()->create(['email' => 'staf-baru@example.com']);
    $inv = $svc->invite($org, ['email' => 'staf-baru@example.com', 'role' => 'staff'], $owner);

    $member = $svc->acceptInvitation($inv, $staf);

    expect($member->role)->toBe('staff')
        ->and($member->status)->toBe('active')
        ->and($staf->refresh()->organizationRole($org->id))->toBe('staff')
        ->and($staf->hasPermissionTo('organization.view'))->toBeTrue()
        ->and($staf->hasPermissionTo('member.view'))->toBeTrue()
        ->and($staf->hasPermissionTo('organization.suspend'))->toBeFalse()
        ->and($inv->refresh()->accepted_at)->not->toBeNull();
});

it('menolak penerimaan undangan oleh email yang berbeda', function () {
    [$org, $owner] = buatOrgDenganOwner();
    $svc = app(MembershipService::class);

    $inv = $svc->invite($org, ['email' => 'tujuan@example.com', 'role' => 'staff'], $owner);
    $orangLain = User::factory()->create(['email' => 'lain@example.com']);

    expect(fn () => $svc->acceptInvitation($inv, $orangLain))->toThrow(HttpException::class);
});

it('menolak penerimaan undangan yang kedaluwarsa', function () {
    [$org, $owner] = buatOrgDenganOwner();
    $svc = app(MembershipService::class);

    $staf = User::factory()->create(['email' => 'kedaluwarsa@example.com']);
    $inv = $svc->invite($org, ['email' => 'kedaluwarsa@example.com', 'role' => 'staff'], $owner);
    $inv->forceFill(['expires_at' => now()->subDay()])->save();

    expect(fn () => $svc->acceptInvitation($inv->refresh(), $staf))->toThrow(HttpException::class);
});

it('menolak perubahan role diri sendiri dengan 403', function () {
    [$org, $owner] = buatOrgDenganOwner();

    $milikSendiri = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $owner->id)->firstOrFail();

    try {
        app(MembershipService::class)->changeRole($milikSendiri, 'staff', $owner);
        $this->fail('Seharusnya melempar HttpException 403.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('mengubah role staff menjadi owner beserta permission', function () {
    [$org, $owner] = buatOrgDenganOwner();
    $svc = app(MembershipService::class);

    $staf = User::factory()->create(['email' => 'naik@example.com']);
    $inv = $svc->invite($org, ['email' => 'naik@example.com', 'role' => 'staff'], $owner);
    $member = $svc->acceptInvitation($inv, $staf);

    $hasil = $svc->changeRole($member, 'owner', $owner);

    expect($hasil->role)->toBe('owner')
        ->and($staf->refresh()->hasPermissionTo('organization.suspend'))->toBeTrue();
});

it('menolak penghapusan owner terakhir', function () {
    [$org, $owner] = buatOrgDenganOwner();

    $milikSendiri = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $owner->id)->firstOrFail();

    expect(fn () => app(MembershipService::class)->removeMember($milikSendiri, $owner))
        ->toThrow(HttpException::class);
});

it('menghapus member staff dan mencabut permission', function () {
    [$org, $owner] = buatOrgDenganOwner();
    $svc = app(MembershipService::class);

    $staf = User::factory()->create(['email' => 'keluar@example.com']);
    $inv = $svc->invite($org, ['email' => 'keluar@example.com', 'role' => 'staff'], $owner);
    $member = $svc->acceptInvitation($inv, $staf);

    $svc->removeMember($member, $owner);

    expect(OrganizationMember::where('organization_id', $org->id)->where('user_id', $staf->id)->count())->toBe(0)
        ->and($staf->refresh()->hasPermissionTo('organization.view'))->toBeFalse();
});

it('menolak keluarnya owner terakhir via removeMember diri sendiri', function () {
    [$org, $owner] = buatOrgDenganOwner();

    $milikSendiri = OrganizationMember::where('organization_id', $org->id)
        ->where('user_id', $owner->id)->firstOrFail();

    expect(fn () => app(MembershipService::class)->removeMember($milikSendiri, $owner))
        ->toThrow(HttpException::class);
});

it('mentransfer kepemilikan dengan menukar role dan permission', function () {
    [$org, $owner] = buatOrgDenganOwner();
    $svc = app(MembershipService::class);

    $staf = User::factory()->create(['email' => 'penerus@example.com']);
    $inv = $svc->invite($org, ['email' => 'penerus@example.com', 'role' => 'staff'], $owner);
    $svc->acceptInvitation($inv, $staf);

    $svc->transferOwnership($org, $staf->refresh(), $owner);

    expect($owner->refresh()->organizationRole($org->id))->toBe('staff')
        ->and($staf->refresh()->organizationRole($org->id))->toBe('owner')
        ->and($staf->hasPermissionTo('organization.suspend'))->toBeTrue()
        ->and($owner->hasPermissionTo('organization.suspend'))->toBeFalse()
        ->and(AuditLog::where('action', 'organization.ownership_transferred')->count())->toBe(1);
});

it('memberikan dan mencabut permission granular pada staff', function () {
    [$org, $owner] = buatOrgDenganOwner();
    $svc = app(MembershipService::class);

    $staf = User::factory()->create(['email' => 'izin@example.com']);
    $inv = $svc->invite($org, ['email' => 'izin@example.com', 'role' => 'staff'], $owner);
    $svc->acceptInvitation($inv, $staf);

    $svc->grantPermission($owner, $staf->refresh(), $org, 'member.invite');
    expect($staf->refresh()->hasPermissionTo('member.invite'))->toBeTrue();

    $svc->revokePermission($owner, $staf->refresh(), $org, 'member.invite');
    expect($staf->refresh()->hasPermissionTo('member.invite'))->toBeFalse();
});

it('menandai undangan ditolak oleh pemilik email', function () {
    [$org, $owner] = buatOrgDenganOwner();
    $svc = app(MembershipService::class);

    $calon = User::factory()->create(['email' => 'tolak@example.com']);
    $inv = $svc->invite($org, ['email' => 'tolak@example.com', 'role' => 'staff'], $owner);

    $svc->declineInvitation($inv, $calon);

    expect($inv->refresh()->declined_at)->not->toBeNull();
});

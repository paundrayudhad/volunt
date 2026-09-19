<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipService
{
    public const GRANULAR = [
        'organization.view',
        'organization.update',
        'member.view',
        'member.invite',
        'member.remove',
        'member.change_role',
        'invitation.manage',
        'request.create',
        'request.review',
        'organization.suspend',
        'organization.archive',
        'audit.read',
        'security.read',
        'event.create',
        'event.view',
        'event.update',
        'event.delete',
        'event.publish',
        'division.manage',
        'role.manage',
        'shift.manage',
        'registration.read',
        'registration.review',
        'assignment.manage',
        'assignment.read',
        'attendance.record',
        'attendance.read',
        'announcement.publish',
        'announcement.read',
    ];

    public const OWNER_PERMS = self::GRANULAR;

    public const STAFF_BASE = ['organization.view', 'member.view', 'event.view'];

    public function __construct(private AuditLogService $audit) {}

    public function syncPermissions(User $user, Organization $org): void
    {
        foreach (self::GRANULAR as $perm) {
            $user->revokePermissionTo($perm);
        }

        $role = $user->organizationRole($org->id);

        if ($role === 'owner') {
            $user->givePermissionTo(self::OWNER_PERMS);
        } elseif ($role === 'staff') {
            $user->givePermissionTo(self::STAFF_BASE);
        }
    }

    public function invite(Organization $org, array $data, User $actor): OrganizationInvitation
    {
        $role = $data['role'] ?? 'staff';
        abort_unless($role === 'staff', 422, 'Undangan hanya untuk role staff.');

        $email = strtolower(trim($data['email'] ?? ''));
        abort_unless($email !== '', 422, 'Email undangan wajib.');

        $sudahMember = OrganizationMember::where('organization_id', $org->id)
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->exists();
        abort_if($sudahMember, 422, 'Pengguna sudah menjadi member organisasi.');

        $pendingAda = OrganizationInvitation::where('organization_id', $org->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->exists();
        abort_if($pendingAda, 422, 'Undangan pending untuk email ini sudah ada.');

        $plain = Str::random(40);

        $inv = OrganizationInvitation::unguarded(fn () => OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => $email,
            'role' => 'staff',
            'token_hash' => hash('sha256', $plain),
            'invited_by' => $actor->id,
        ]));

        $inv->plain_token = $plain;

        $this->audit->record($actor, 'member.invited', OrganizationInvitation::class, $inv->id, [
            'organization_id' => $org->id,
            'new' => ['email' => $email, 'role' => 'staff'],
        ]);

        return $inv;
    }

    public function acceptInvitation(OrganizationInvitation $inv, User $user): OrganizationMember
    {
        abort_unless($inv->accepted_at === null && $inv->declined_at === null, 422, 'Undangan sudah diproses.');
        abort_if($inv->isExpired(), 422, 'Undangan sudah kedaluwarsa.');

        /** @phpstan-impure */
        $emailCocok = fn (): bool => strtolower((string) $inv->email) === strtolower((string) $user->email);
        abort_unless($emailCocok(), 403, 'Undangan ini bukan untuk akun Anda.');

        $sudahMember = OrganizationMember::where('organization_id', $inv->organization_id)
            ->where('user_id', $user->id)
            ->exists();
        abort_if($sudahMember, 422, 'Anda sudah menjadi member organisasi.');

        return DB::transaction(function () use ($inv, $user) {
            $member = OrganizationMember::unguarded(fn () => OrganizationMember::create([
                'organization_id' => $inv->organization_id,
                'user_id' => $user->id,
                'role' => 'staff',
                'status' => 'active',
                'joined_at' => now(),
            ]));

            $this->syncPermissions($user->refresh(), $inv->organization);

            $inv->forceFill(['accepted_at' => now()])->save();

            $this->audit->record($user, 'member.joined', OrganizationMember::class, $member->id, [
                'organization_id' => $inv->organization_id,
            ]);

            return $member;
        });
    }

    public function declineInvitation(OrganizationInvitation $inv, User $user): void
    {
        abort_unless($inv->accepted_at === null && $inv->declined_at === null, 422, 'Undangan sudah diproses.');

        /** @phpstan-impure */
        $emailCocok = fn (): bool => strtolower((string) $inv->email) === strtolower((string) $user->email);
        abort_unless($emailCocok(), 403, 'Undangan ini bukan untuk akun Anda.');

        $inv->forceFill(['declined_at' => now()])->save();

        $this->audit->record($user, 'member.invitation_declined', OrganizationInvitation::class, $inv->id, [
            'organization_id' => $inv->organization_id,
        ]);
    }

    public function changeRole(OrganizationMember $member, string $role, User $actor): OrganizationMember
    {
        abort_if($member->user_id === $actor->id, 403, 'Tidak boleh mengubah role sendiri.');
        abort_unless(in_array($role, ['owner', 'staff'], true), 422, 'Role tidak valid.');

        $old = $member->role;
        $member->forceFill(['role' => $role])->save();

        $this->syncPermissions($member->user, $member->organization);

        $this->audit->record($actor, 'member.role_changed', OrganizationMember::class, $member->id, [
            'organization_id' => $member->organization_id,
            'old' => ['role' => $old],
            'new' => ['role' => $role],
        ]);

        return $member->refresh();
    }

    public function removeMember(OrganizationMember $member, User $actor): void
    {
        abort_if($member->user_id === $actor->id, 403, 'Tidak boleh menghapus diri sendiri. Gunakan fitur keluar.');

        if ($member->role === 'owner' && $member->status === 'active') {
            $jumlahOwner = OrganizationMember::where('organization_id', $member->organization_id)
                ->where('role', 'owner')
                ->where('status', 'active')
                ->count();
            abort_if($jumlahOwner <= 1, 422, 'Owner terakhir tidak boleh dihapus.');
        }

        $user = $member->user;
        $org = $member->organization;
        $memberId = $member->id;

        $member->delete();

        $this->syncPermissions($user, $org);

        $this->audit->record($actor, 'member.removed', OrganizationMember::class, $memberId, [
            'organization_id' => $org->id,
            'old' => ['user_id' => $user->id, 'role' => $member->role],
        ]);
    }

    public function grantPermission(User $actor, User $user, Organization $org, string $permission): void
    {
        abort_unless(in_array($permission, self::GRANULAR, true), 422, 'Permission tidak dikenal.');
        abort_unless($user->organizationRole($org->id) === 'staff', 422, 'Target harus staff aktif organisasi ini.');
        abort_unless($actor->organizationRole($org->id) === 'owner', 403, 'Hanya owner yang boleh memberi permission.');

        $user->givePermissionTo($permission);

        $this->audit->record($actor, 'member.permission_granted', User::class, $user->id, [
            'organization_id' => $org->id,
            'new' => ['permission' => $permission],
        ]);
    }

    public function revokePermission(User $actor, User $user, Organization $org, string $permission): void
    {
        abort_unless(in_array($permission, self::GRANULAR, true), 422, 'Permission tidak dikenal.');
        abort_unless($user->organizationRole($org->id) === 'staff', 422, 'Target harus staff aktif organisasi ini.');
        abort_unless($actor->organizationRole($org->id) === 'owner', 403, 'Hanya owner yang boleh mencabut permission.');
        abort_if(in_array($permission, self::STAFF_BASE, true), 422, 'Permission dasar staff tidak boleh dicabut.');

        $user->revokePermissionTo($permission);

        $this->audit->record($actor, 'member.permission_revoked', User::class, $user->id, [
            'organization_id' => $org->id,
            'old' => ['permission' => $permission],
        ]);
    }

    public function transferOwnership(Organization $org, User $newOwner, User $actor): void
    {
        abort_unless($actor->organizationRole($org->id) === 'owner', 403, 'Hanya owner yang boleh mentransfer kepemilikan.');
        abort_unless($newOwner->organizationRole($org->id) === 'staff', 422, 'Penerima harus staff aktif organisasi ini.');

        DB::transaction(function () use ($org, $newOwner, $actor) {
            $actorMember = OrganizationMember::where('organization_id', $org->id)
                ->where('user_id', $actor->id)
                ->where('status', 'active')
                ->firstOrFail();
            $newOwnerMember = OrganizationMember::where('organization_id', $org->id)
                ->where('user_id', $newOwner->id)
                ->where('status', 'active')
                ->firstOrFail();

            $actorMember->forceFill(['role' => 'staff'])->save();
            $newOwnerMember->forceFill(['role' => 'owner'])->save();

            $this->syncPermissions($actor->refresh(), $org);
            $this->syncPermissions($newOwner->refresh(), $org);

            $this->audit->record($actor, 'organization.ownership_transferred', Organization::class, $org->id, [
                'organization_id' => $org->id,
                'old' => ['owner_id' => $actor->id],
                'new' => ['owner_id' => $newOwner->id],
            ]);
        });
    }
}

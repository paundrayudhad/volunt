<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function __construct(private AuditLogService $audit) {}

    public function request(array $data, User $user): OrganizationRequest
    {
        if (OrganizationRequest::where('user_id', $user->id)->where('status', 'pending')->count() >= 3) {
            throw ValidationException::withMessages(['name' => 'Maksimal 3 pengajuan pending.']);
        }

        $req = OrganizationRequest::unguarded(fn () => OrganizationRequest::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'contact' => $data['contact'] ?? null,
            'user_id' => $user->id,
            'status' => 'pending',
        ]));

        $this->audit->record($user, 'organization.requested', OrganizationRequest::class, $req->id);

        return $req;
    }

    public function approve(OrganizationRequest $req, User $admin): Organization
    {
        abort_unless($req->isPending(), 422, 'Pengajuan sudah diproses.');

        return DB::transaction(function () use ($req, $admin) {
            $org = Organization::unguarded(fn () => Organization::create([
                'name' => $req->name,
                'slug' => $req->slug,
                'description' => $req->description,
                'status' => 'active',
            ]));

            OrganizationMember::unguarded(fn () => $org->members()->create([
                'user_id' => $req->user_id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]));

            app(MembershipService::class)->syncPermissions($req->user, $org);

            $req->forceFill([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->record($admin, 'organization.approved', Organization::class, $org->id, ['organization_id' => $org->id]);

            return $org;
        });
    }

    public function reject(OrganizationRequest $req, User $admin, string $reason): OrganizationRequest
    {
        abort_unless($req->isPending(), 422, 'Pengajuan sudah diproses.');
        abort_unless(trim($reason) !== '', 422, 'Alasan penolakan wajib.');

        $req->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $this->audit->record($admin, 'organization.rejected', OrganizationRequest::class, $req->id);

        return $req->refresh();
    }

    public function suspend(Organization $org, User $admin, string $reason): Organization
    {
        abort_unless(trim($reason) !== '', 422, 'Alasan penangguhan wajib.');
        abort_if($org->status === 'suspended', 422, 'Organisasi sudah ditangguhkan.');

        $old = $org->status;
        $org->forceFill(['status' => 'suspended'])->save();

        $this->audit->record($admin, 'organization.suspended', Organization::class, $org->id, [
            'organization_id' => $org->id,
            'old' => ['status' => $old],
            'new' => ['status' => 'suspended', 'reason' => $reason],
        ]);

        return $org->refresh();
    }

    public function archive(Organization $org, User $admin, string $reason): Organization
    {
        abort_unless(trim($reason) !== '', 422, 'Alasan pengarsipan wajib.');
        abort_if($org->status === 'archived', 422, 'Organisasi sudah diarsipkan.');

        $old = $org->status;
        $org->forceFill(['status' => 'archived'])->save();

        $this->audit->record($admin, 'organization.archived', Organization::class, $org->id, [
            'organization_id' => $org->id,
            'old' => ['status' => $old],
            'new' => ['status' => 'archived', 'reason' => $reason],
        ]);

        return $org->refresh();
    }

    public function activate(Organization $org, User $admin): Organization
    {
        abort_unless(in_array($org->status, ['suspended', 'archived'], true), 422, 'Hanya organisasi yang ditangguhkan atau diarsipkan yang dapat diaktifkan.');

        $old = $org->status;
        $org->forceFill(['status' => 'active'])->save();

        $this->audit->record($admin, 'organization.activated', Organization::class, $org->id, [
            'organization_id' => $org->id,
            'old' => ['status' => $old],
            'new' => ['status' => 'active'],
        ]);

        return $org->refresh();
    }

    public function updateProfile(Organization $org, array $data, User $actor): Organization
    {
        $allowed = ['name', 'slug', 'logo_path', 'description', 'email', 'phone', 'website', 'social_links'];
        $perubahan = array_intersect_key($data, array_flip($allowed));

        abort_if($perubahan === [], 422, 'Tidak ada data profil yang dapat diperbarui.');

        $old = $org->only(array_keys($perubahan));
        $org->fill($perubahan)->save();

        $this->audit->record($actor, 'organization.updated', Organization::class, $org->id, [
            'organization_id' => $org->id,
            'old' => $old,
            'new' => $perubahan,
        ]);

        return $org->refresh();
    }
}

<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id);
    }

    public function view(User $user, OrganizationMember $member): bool
    {
        return $user->belongsToOrganization($member->organization_id);
    }

    public function invite(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id)
            && $user->can('member.invite');
    }

    public function update(User $user, OrganizationMember $member): bool
    {
        return $user->belongsToOrganization($member->organization_id)
            && $user->can('member.change_role')
            && $member->user_id !== $user->id;
    }

    public function remove(User $user, OrganizationMember $member): bool
    {
        return $user->belongsToOrganization($member->organization_id)
            && $user->can('member.remove')
            && $member->user_id !== $user->id;
    }
}

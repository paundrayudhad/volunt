<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id);
    }

    public function update(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id)
            && $user->can('organization.update');
    }

    public function transfer(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id)
            && $user->organizationRole($org->id) === 'owner';
    }

    public function suspend(User $user, Organization $org): bool
    {
        return $user->hasRole('super_admin');
    }

    public function archive(User $user, Organization $org): bool
    {
        return $user->hasRole('super_admin');
    }

    public function searchTalent(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id)
            && $user->can('talent.search');
    }

    public function viewTalent(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id)
            && $user->can('talent.search');
    }

    public function inviteTalent(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id)
            && $user->can('talent.invite');
    }
}

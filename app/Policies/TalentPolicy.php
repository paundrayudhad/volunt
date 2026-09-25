<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class TalentPolicy
{
    public function search(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization->id)
            && $user->can('talent.search');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization->id)
            && $user->can('talent.search');
    }

    public function invite(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization->id)
            && $user->can('talent.invite');
    }
}

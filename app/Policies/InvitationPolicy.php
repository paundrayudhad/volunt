<?php

namespace App\Policies;

use App\Models\OrganizationInvitation;
use App\Models\User;

class InvitationPolicy
{
    public function manage(User $user, OrganizationInvitation $invitation): bool
    {
        return $user->belongsToOrganization($invitation->organization_id)
            && $user->can('invitation.manage');
    }

    public function accept(User $user, OrganizationInvitation $invitation): bool
    {
        return strtolower($user->email) === strtolower($invitation->email);
    }

    public function decline(User $user, OrganizationInvitation $invitation): bool
    {
        return strtolower($user->email) === strtolower($invitation->email);
    }
}

<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;

class RegistrationPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && $user->can('registration.read');
    }

    public function view(User $user, Registration $registration): bool
    {
        return $user->belongsToOrganization($registration->event->organization_id)
            && $user->can('registration.read');
    }

    public function review(User $user, Registration $registration): bool
    {
        return $user->belongsToOrganization($registration->event->organization_id)
            && $user->can('registration.review');
    }
}

<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\Event;
use App\Models\User;

class CertificatePolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && $user->can('certificate.read');
    }

    public function view(User $user, Certificate $certificate): bool
    {
        return $user->belongsToOrganization($certificate->event->organization_id)
            && $user->can('certificate.read');
    }

    public function issue(User $user, Certificate|Event $subject): bool
    {
        $event = $subject instanceof Event ? $subject : $subject->event;

        return $user->belongsToOrganization($event->organization_id)
            && $user->can('certificate.issue');
    }

    public function revoke(User $user, Certificate $certificate): bool
    {
        return $user->belongsToOrganization($certificate->event->organization_id)
            && $user->can('certificate.revoke');
    }
}

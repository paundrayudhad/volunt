<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\Event;
use App\Models\User;

class AssignmentPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && $user->can('assignment.read');
    }

    public function view(User $user, Assignment $assignment): bool
    {
        return $user->belongsToOrganization($assignment->event->organization_id)
            && $user->can('assignment.read');
    }

    public function manage(User $user, Assignment|Event $subject): bool
    {
        $event = $subject instanceof Event ? $subject : $subject->event;

        return $user->belongsToOrganization($event->organization_id)
            && $user->can('assignment.manage');
    }
}

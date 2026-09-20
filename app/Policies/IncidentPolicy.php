<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && ($user->can('incident.report') || $user->can('incident.manage'));
    }

    public function view(User $user, Incident $insiden): bool
    {
        $event = $insiden->event;

        return $event !== null
            && $user->belongsToOrganization($event->organization_id)
            && ($user->can('incident.report') || $user->can('incident.manage'));
    }

    public function manage(User $user, Incident|Event $subject): bool
    {
        $event = $subject instanceof Event ? $subject : $subject->event;

        return $event !== null
            && $user->belongsToOrganization($event->organization_id)
            && $user->can('incident.manage');
    }
}

<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventShift;
use App\Models\User;

class ShiftPolicy
{
    public function manage(User $user, EventShift|Event $target): bool
    {
        $event = $target instanceof Event ? $target : $target->event;

        return $event instanceof Event
            && $user->belongsToOrganization($event->organization_id)
            && $user->can('shift.manage');
    }
}

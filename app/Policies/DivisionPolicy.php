<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\User;

class DivisionPolicy
{
    public function manage(User $user, EventDivision|Event $target): bool
    {
        $event = $target instanceof Event ? $target : $target->event;

        return $event instanceof Event
            && $user->belongsToOrganization($event->organization_id)
            && $user->can('division.manage');
    }
}

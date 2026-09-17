<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventRole;
use App\Models\User;

class RolePolicy
{
    public function manage(User $user, EventRole|Event $target): bool
    {
        $event = $target instanceof Event ? $target : $target->event;

        return $event instanceof Event
            && $user->belongsToOrganization($event->organization_id)
            && $user->can('role.manage');
    }
}

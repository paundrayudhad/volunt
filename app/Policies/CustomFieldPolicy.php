<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\User;

class CustomFieldPolicy
{
    public function manage(User $user, EventCustomField|Event $target): bool
    {
        $event = $target instanceof Event ? $target : $target->event;

        return $event instanceof Event
            && $user->belongsToOrganization($event->organization_id)
            && $user->can('role.manage');
    }
}

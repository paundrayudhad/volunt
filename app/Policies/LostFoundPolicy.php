<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\LostFoundItem;
use App\Models\User;

class LostFoundPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && ($user->can('lostfound.manage') || $user->can('incident.report'));
    }

    public function view(User $user, LostFoundItem $item): bool
    {
        $event = $item->event;

        return $event !== null
            && $user->belongsToOrganization($event->organization_id)
            && ($user->can('lostfound.manage') || $user->can('incident.report'));
    }

    public function manage(User $user, LostFoundItem|Event $subject): bool
    {
        $event = $subject instanceof Event ? $subject : $subject->event;

        return $event !== null
            && $user->belongsToOrganization($event->organization_id)
            && $user->can('lostfound.manage');
    }
}

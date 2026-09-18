<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && $user->can('announcement.read');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->belongsToOrganization($announcement->event->organization_id)
            && $user->can('announcement.read');
    }

    public function publish(User $user, Announcement|Event $subject): bool
    {
        $event = $subject instanceof Event ? $subject : $subject->event;

        return $user->belongsToOrganization($event->organization_id)
            && $user->can('announcement.publish');
    }
}

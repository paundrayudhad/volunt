<?php

namespace App\Policies;

use App\Models\Artist;
use App\Models\Event;
use App\Models\User;

class ArtistPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && ($user->can('artist.read') || $user->can('artist.liaise') || $user->can('artist.manage'));
    }

    public function view(User $user, Artist $artis): bool
    {
        $event = $artis->event;

        return $event !== null
            && $user->belongsToOrganization($event->organization_id)
            && ($user->can('artist.read') || $user->can('artist.liaise') || $user->can('artist.manage'));
    }

    public function manage(User $user, Artist|Event $subject): bool
    {
        $event = $subject instanceof Event ? $subject : $subject->event;

        return $event !== null
            && $user->belongsToOrganization($event->organization_id)
            && $user->can('artist.manage');
    }
}

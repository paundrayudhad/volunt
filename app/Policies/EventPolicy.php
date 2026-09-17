<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id);
    }

    public function view(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id);
    }

    public function create(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id)
            && $user->can('event.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && $user->can('event.update');
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && $user->organizationRole($event->organization_id) === 'owner';
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && $user->can('event.publish');
    }
}

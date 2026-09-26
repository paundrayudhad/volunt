<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

class AnalyticsPolicy
{
    public function viewEventAnalytics(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && $user->can('analytics.view');
    }

    public function viewOrgAnalytics(User $user, Organization $org): bool
    {
        return $user->belongsToOrganization($org->id)
            && $user->can('analytics.view');
    }

    public function exportEventData(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organization_id)
            && $user->can('analytics.export');
    }
}

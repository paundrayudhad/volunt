<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class OrganizationAnalyticsService
{
    /**
     * @return array{
     *     total_events: int,
     *     published_events: int,
     *     completed_events: int,
     *     active_events: int,
     *     unique_volunteers_count: int,
     *     total_accepted_registrations: int
     * }
     */
    public function getOrganizationSummary(Organization $organization): array
    {
        $eventCounts = DB::table('events')
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'published') AS published,
                COUNT(*) FILTER (WHERE status = 'completed') AS completed,
                COUNT(*) FILTER (WHERE status = 'ongoing') AS ongoing
            ")
            ->first();

        $volStats = DB::table('registrations')
            ->join('events', 'registrations.event_id', '=', 'events.id')
            ->where('events.organization_id', $organization->id)
            ->where('registrations.status', 'accepted')
            ->selectRaw('
                COUNT(DISTINCT registrations.user_id) AS unique_volunteers,
                COUNT(registrations.id) AS total_accepted
            ')
            ->first();

        $total = (int) ($eventCounts->total ?? 0);
        $published = (int) ($eventCounts->published ?? 0);
        $completed = (int) ($eventCounts->completed ?? 0);
        $ongoing = (int) ($eventCounts->ongoing ?? 0);

        return [
            'total_events' => $total,
            'published_events' => $published,
            'completed_events' => $completed,
            'active_events' => $published + $ongoing,
            'unique_volunteers_count' => (int) ($volStats->unique_volunteers ?? 0),
            'total_accepted_registrations' => (int) ($volStats->total_accepted ?? 0),
        ];
    }
}

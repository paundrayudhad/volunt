<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Services\EventAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventAnalyticsController extends Controller
{
    public function __construct(private EventAnalyticsService $analytics) {}

    public function index(Request $request, Organization $organization, Event $event): View
    {
        abort_unless($event->organization_id === $organization->id, 404);

        $user = $request->user();
        abort_unless(
            $user !== null
            && $user->belongsToOrganization($organization->id)
            && $user->can('analytics.view'),
            403
        );

        $summary = $this->analytics->getEventSummary($event);

        return view('organizer.events.analytics.index', [
            'organization' => $organization,
            'event' => $event,
            'summary' => $summary,
        ]);
    }
}

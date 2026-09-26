<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrganizationAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationAnalyticsController extends Controller
{
    public function __construct(private OrganizationAnalyticsService $analytics) {}

    public function index(Request $request, Organization $organization): View
    {
        $user = $request->user();
        abort_unless(
            $user !== null
            && $user->belongsToOrganization($organization->id)
            && $user->can('analytics.view'),
            403
        );

        $summary = $this->analytics->getOrganizationSummary($organization);

        return view('organizer.analytics.index', [
            'organization' => $organization,
            'summary' => $summary,
        ]);
    }
}

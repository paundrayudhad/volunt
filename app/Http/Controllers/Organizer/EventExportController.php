<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Services\DataExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EventExportController extends Controller
{
    public function __construct(private DataExportService $exportService) {}

    public function export(Request $request, Organization $organization, Event $event, string $dataset): Response
    {
        abort_unless($event->organization_id === $organization->id, 404);

        $user = $request->user();
        abort_unless(
            $user !== null
            && $user->belongsToOrganization($organization->id)
            && $user->can('analytics.export'),
            403
        );

        $format = strtolower((string) $request->query('format', 'csv'));

        if ($format === 'xlsx') {
            return $this->exportService->exportXlsx($event, $dataset, $user);
        }

        return $this->exportService->exportCsv($event, $dataset, $user);
    }
}

<?php

namespace App\Http\Controllers\Organizer;

use App\Exceptions\ShiftFullException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRequest;
use App\Http\Requests\BulkAssignRequest;
use App\Http\Requests\CancelAssignmentRequest;
use App\Http\Requests\ReassignAssignmentRequest;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\Registration;
use App\Services\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssignmentController extends Controller
{
    public function __construct(private AssignmentService $assignments) {}

    public function index(Request $request, Organization $organization, Event $event): View
    {
        Gate::authorize('viewAny', [Assignment::class, $event]);

        $status = $request->query('status');
        $shift = $request->query('shift');

        $items = Assignment::where('event_id', $event->id)
            ->with(['user', 'role', 'shift'])
            ->when(
                in_array($status, ['assigned', 'reassigned', 'confirmed', 'completed', 'cancelled'], true),
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                is_numeric($shift),
                fn ($query) => $query->where('shift_id', (int) $shift)
            )
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('organizer.events.assignments.index', [
            'org' => $organization,
            'event' => $event,
            'assignments' => $items,
            'selectedStatus' => in_array($status, ['assigned', 'reassigned', 'confirmed', 'completed', 'cancelled'], true) ? $status : '',
            'statusOptions' => ['assigned', 'reassigned', 'confirmed', 'completed', 'cancelled'],
            'selectedShift' => is_numeric($shift) ? (string) $shift : '',
            'shifts' => EventShift::where('event_id', $event->id)->orderBy('start_at')->get(),
            'candidates' => Registration::where('event_id', $event->id)
                ->where('status', 'accepted')
                ->whereDoesntHave('assignment', fn ($q) => $q->whereIn('status', Assignment::ACTIVE))
                ->with(['user', 'role'])
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
        ]);
    }

    public function show(Organization $organization, Event $event, Assignment $assignment): View
    {
        Gate::authorize('view', $assignment);

        $assignment->load(['user', 'role', 'shift', 'histories']);

        return view('organizer.events.assignments.show', [
            'org' => $organization,
            'event' => $event,
            'assignment' => $assignment,
            'shifts' => EventShift::where('event_id', $event->id)->orderBy('start_at')->get(),
        ]);
    }

    public function assign(AssignRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        $registration = Registration::whereKey($valid['registration_id'])
            ->where('event_id', $event->id)
            ->first();
        abort_if($registration === null, 404);

        $shift = EventShift::whereKey($valid['shift_id'])
            ->where('event_id', $event->id)
            ->first();
        abort_if($shift === null, 404);

        try {
            $assignment = $this->assignments->assign($registration, $shift, $request->user(), $valid['location'] ?? null);
        } catch (ShiftFullException $e) {
            return back()->withInput()->withErrors(['shift_id' => $e->getMessage()]);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['registration_id' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.assignments.show', [$organization->slug, $event->slug, $assignment->id])
            ->with('status', 'Relawan berhasil ditugaskan ke shift.');
    }

    public function reassign(ReassignAssignmentRequest $request, Organization $organization, Event $event, Assignment $assignment): RedirectResponse
    {
        $valid = $request->validated();

        $shift = EventShift::whereKey($valid['shift_id'])
            ->where('event_id', $event->id)
            ->first();
        abort_if($shift === null, 404);

        try {
            $this->assignments->reassign($assignment, $shift, $request->user());
        } catch (ShiftFullException $e) {
            return back()->withInput()->withErrors(['shift_id' => $e->getMessage()]);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['shift_id' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.assignments.show', [$organization->slug, $event->slug, $assignment->id])
            ->with('status', 'Assignment berhasil dipindahkan ke shift lain.');
    }

    public function confirm(Request $request, Organization $organization, Event $event, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('manage', $assignment);

        try {
            $this->assignments->confirm($assignment, $request->user());
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.assignments.show', [$organization->slug, $event->slug, $assignment->id])
            ->with('status', 'Assignment berhasil dikonfirmasi.');
    }

    public function cancel(CancelAssignmentRequest $request, Organization $organization, Event $event, Assignment $assignment): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $this->assignments->cancel($assignment, $request->user(), $valid['reason'] ?? null);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.assignments.show', [$organization->slug, $event->slug, $assignment->id])
            ->with('status', 'Assignment berhasil dibatalkan.');
    }

    public function bulkAssign(BulkAssignRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        $shift = EventShift::whereKey($valid['shift_id'])
            ->where('event_id', $event->id)
            ->first();
        abort_if($shift === null, 404);

        try {
            $this->assignments->bulkAssign($event, array_map('intval', $valid['ids']), $shift, $request->user());
        } catch (ShiftFullException $e) {
            return back()->withInput()->withErrors(['shift_id' => $e->getMessage()]);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['ids' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.assignments.index', [$organization->slug, $event->slug])
            ->with('status', 'Penugasan massal berhasil diterapkan.');
    }
}

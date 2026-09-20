<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignIncidentRequest;
use App\Http\Requests\ReopenIncidentRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\TransitionIncidentRequest;
use App\Models\Event;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IncidentController extends Controller
{
    public function __construct(private IncidentService $insiden, private AuditLogService $audit) {}

    public function index(Request $request, Organization $organization, Event $event): View
    {
        Gate::authorize('viewAny', [Incident::class, $event]);

        $items = Incident::where('event_id', $event->id)
            ->with(['reporter', 'assignee'])
            ->when($request->query('category'), fn ($query, $kategori) => $query->where('category', $kategori))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('priority'), fn ($query, $prioritas) => $query->where('priority', $prioritas))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('organizer.events.incidents.index', [
            'org' => $organization,
            'event' => $event,
            'incidents' => $items,
        ]);
    }

    public function show(Organization $organization, Event $event, Incident $insiden): View
    {
        Gate::authorize('view', $insiden);

        $insiden->load(['reporter', 'assignee', 'lostFoundItem', 'histories.actor']);

        return view('organizer.events.incidents.show', [
            'org' => $organization,
            'event' => $event,
            'incident' => $insiden,
        ]);
    }

    public function store(StoreIncidentRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $insiden = $this->insiden->report($event, $request->user(), $valid);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['description' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.incidents.show', [$organization->slug, $event->slug, $insiden->id])
            ->with('status', 'Insiden berhasil dilaporkan.');
    }

    public function assign(AssignIncidentRequest $request, Organization $organization, Event $event, Incident $insiden): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $tugas = User::whereKey($valid['assignee_id'])->firstOrFail();
            $this->insiden->assign($insiden, $request->user(), $tugas);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['assignee_id' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.incidents.show', [$organization->slug, $event->slug, $insiden->id])
            ->with('status', 'Insiden berhasil ditugaskan.');
    }

    public function transition(TransitionIncidentRequest $request, Organization $organization, Event $event, Incident $insiden): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $this->insiden->transition($insiden, $request->user(), $valid['to'], $valid['note'] ?? null);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['to' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.incidents.show', [$organization->slug, $event->slug, $insiden->id])
            ->with('status', 'Status insiden berhasil diperbarui.');
    }

    public function reopen(ReopenIncidentRequest $request, Organization $organization, Event $event, Incident $insiden): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $this->insiden->reopen($insiden, $request->user(), $valid['reason']);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.incidents.show', [$organization->slug, $event->slug, $insiden->id])
            ->with('status', 'Insiden berhasil dibuka ulang.');
    }

    public function destroy(Organization $organization, Event $event, Incident $insiden): RedirectResponse
    {
        Gate::authorize('manage', $insiden);

        $insiden->delete();
        $this->audit->record(request()->user(), 'incident.deleted', Incident::class, $insiden->id, [
            'event_id' => $event->id,
        ]);

        return redirect()->route('organizer.events.incidents.index', [$organization->slug, $event->slug])
            ->with('status', 'Insiden diarsipkan.');
    }
}

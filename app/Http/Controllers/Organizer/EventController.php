<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\TransitionEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\Organization;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventController extends Controller
{
    public function __construct(private EventService $events) {}

    public function index(Organization $organization): View
    {
        Gate::authorize('viewAny', [Event::class, $organization]);

        $acara = $organization->events()->orderByDesc('id')->paginate(12);

        return view('organizer.events.index', ['org' => $organization, 'acara' => $acara]);
    }

    public function create(Organization $organization): View
    {
        Gate::authorize('create', [Event::class, $organization]);

        return view('organizer.events.create', ['org' => $organization]);
    }

    public function store(StoreEventRequest $request, Organization $organization): RedirectResponse
    {
        $event = $this->events->createEvent($organization, $request->validated(), $request->user());

        return redirect()->route('organizer.events.show', [$organization->slug, $event->slug])
            ->with('status', 'Event berhasil dibuat.');
    }

    public function show(Organization $organization, Event $event): View
    {
        Gate::authorize('view', $event);

        $event->load(['divisions.roles', 'roles', 'shifts']);
        $event->loadCount('artists');

        $event->setRelation('artists', $event->artists()
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderByDesc('id')
            ->take(5)
            ->get());

        return view('organizer.events.show', ['org' => $organization, 'event' => $event]);
    }

    public function edit(Organization $organization, Event $event): View
    {
        Gate::authorize('update', $event);

        return view('organizer.events.edit', ['org' => $organization, 'event' => $event]);
    }

    public function update(UpdateEventRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        try {
            $event = $this->events->updateEvent($event, $request->validated(), $request->user());
        } catch (HttpException $e) {
            return back()->withInput()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.show', [$organization->slug, $event->slug])
            ->with('status', 'Event berhasil diperbarui.');
    }

    public function destroy(Organization $organization, Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        try {
            $this->events->deleteEvent($event, request()->user());
        } catch (HttpException $e) {
            return back()->withErrors(['event' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.index', $organization->slug)
            ->with('status', 'Event berhasil dihapus.');
    }

    public function transition(TransitionEventRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $event = $this->events->transitionTo($event, $valid['status'], $request->user(), $valid['reason'] ?? null);
        } catch (HttpException $e) {
            return back()->withInput()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.show', [$organization->slug, $event->slug])
            ->with('status', 'Status event diubah menjadi '.$event->status.'.');
    }
}

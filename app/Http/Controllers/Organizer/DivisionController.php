<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageDivisionRequest;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\Organization;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DivisionController extends Controller
{
    public function __construct(private EventService $events) {}

    public function index(Organization $organization, Event $event): View
    {
        Gate::authorize('manage', [EventDivision::class, $event]);

        $divisi = $event->divisions()->orderBy('id')->get();

        return view('organizer.divisions.index', ['org' => $organization, 'event' => $event, 'divisi' => $divisi]);
    }

    public function create(Organization $organization, Event $event): View
    {
        Gate::authorize('manage', [EventDivision::class, $event]);

        return view('organizer.divisions.create', ['org' => $organization, 'event' => $event]);
    }

    public function store(ManageDivisionRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $divisi = $this->events->createDivision($event, $request->validated(), $request->user());

        return redirect()->route('organizer.events.divisions.show', [$organization->slug, $event->slug, $divisi->id])
            ->with('status', 'Divisi berhasil dibuat.');
    }

    public function show(Organization $organization, Event $event, EventDivision $division): View
    {
        Gate::authorize('manage', $division);

        $division->load(['roles', 'shifts']);

        return view('organizer.divisions.show', ['org' => $organization, 'event' => $event, 'divisi' => $division]);
    }

    public function edit(Organization $organization, Event $event, EventDivision $division): View
    {
        Gate::authorize('manage', $division);

        return view('organizer.divisions.edit', ['org' => $organization, 'event' => $event, 'divisi' => $division]);
    }

    public function update(ManageDivisionRequest $request, Organization $organization, Event $event, EventDivision $division): RedirectResponse
    {
        $valid = $request->validated();
        if ($request->has('supervisor_id') && ! array_key_exists('supervisor_id', $valid)) {
            $valid['supervisor_id'] = null;
        }

        try {
            $this->events->updateDivision($division, $valid, $request->user());
        } catch (HttpException $e) {
            return back()->withInput()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.divisions.show', [$organization->slug, $event->slug, $division->id])
            ->with('status', 'Divisi berhasil diperbarui.');
    }

    public function destroy(Organization $organization, Event $event, EventDivision $division): RedirectResponse
    {
        Gate::authorize('manage', $division);

        try {
            $this->events->deleteDivision($division, request()->user());
        } catch (HttpException $e) {
            return back()->withErrors(['divisi' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.divisions.index', [$organization->slug, $event->slug])
            ->with('status', 'Divisi berhasil dihapus.');
    }
}

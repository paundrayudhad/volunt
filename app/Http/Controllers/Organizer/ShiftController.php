<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageShiftRequest;
use App\Models\Event;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShiftController extends Controller
{
    public function __construct(private EventService $events) {}

    public function index(Organization $organization, Event $event): View
    {
        Gate::authorize('manage', [EventShift::class, $event]);

        $jadwal = $event->shifts()->with(['division', 'role'])->orderBy('start_at')->get();

        return view('organizer.shifts.index', ['org' => $organization, 'event' => $event, 'jadwal' => $jadwal]);
    }

    public function create(Organization $organization, Event $event): View
    {
        Gate::authorize('manage', [EventShift::class, $event]);

        $divisi = $event->divisions()->orderBy('name')->get();
        $peran = $event->roles()->orderBy('name')->get();

        return view('organizer.shifts.create', ['org' => $organization, 'event' => $event, 'divisi' => $divisi, 'peran' => $peran]);
    }

    public function store(ManageShiftRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();
        $divisi = $event->divisions()->findOrFail($valid['division_id'] ?? 0);
        /** @var EventRole|null $peran */
        $peran = array_key_exists('role_id', $valid) && $valid['role_id'] !== null
            ? $event->roles()->findOrFail($valid['role_id'])
            : null;

        try {
            $shift = $this->events->createShift($event, $divisi, $peran, $valid, $request->user());
        } catch (HttpException $e) {
            return back()->withInput()->withErrors(['start_at' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.shifts.show', [$organization->slug, $event->slug, $shift->id])
            ->with('status', 'Shift berhasil dibuat.');
    }

    public function show(Organization $organization, Event $event, EventShift $shift): View
    {
        Gate::authorize('manage', $shift);

        $shift->load(['division', 'role']);

        return view('organizer.shifts.show', ['org' => $organization, 'event' => $event, 'shift' => $shift]);
    }

    public function edit(Organization $organization, Event $event, EventShift $shift): View
    {
        Gate::authorize('manage', $shift);

        $divisi = $event->divisions()->orderBy('name')->get();
        $peran = $event->roles()->orderBy('name')->get();

        return view('organizer.shifts.edit', ['org' => $organization, 'event' => $event, 'shift' => $shift, 'divisi' => $divisi, 'peran' => $peran]);
    }

    public function update(ManageShiftRequest $request, Organization $organization, Event $event, EventShift $shift): RedirectResponse
    {
        $valid = $request->validated();

        if (array_key_exists('division_id', $valid)) {
            $divisi = $event->divisions()->findOrFail($valid['division_id']);
            $shift->forceFill(['division_id' => $divisi->id]);
            unset($valid['division_id']);
        }
        if ($request->has('role_id') && ! array_key_exists('role_id', $valid)) {
            $valid['role_id'] = null;
        }
        if ($request->has('supervisor_id') && ! array_key_exists('supervisor_id', $valid)) {
            $valid['supervisor_id'] = null;
        }

        try {
            $this->events->updateShift($shift, $valid, $request->user());
        } catch (HttpException $e) {
            return back()->withInput()->withErrors(['start_at' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.shifts.show', [$organization->slug, $event->slug, $shift->id])
            ->with('status', 'Shift berhasil diperbarui.');
    }

    public function destroy(Organization $organization, Event $event, EventShift $shift): RedirectResponse
    {
        Gate::authorize('manage', $shift);

        try {
            $this->events->deleteShift($shift, request()->user());
        } catch (HttpException $e) {
            return back()->withErrors(['shift' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.shifts.index', [$organization->slug, $event->slug])
            ->with('status', 'Shift berhasil dihapus.');
    }
}

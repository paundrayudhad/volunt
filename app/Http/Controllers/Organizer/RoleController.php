<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageRoleRequest;
use App\Models\Event;
use App\Models\EventRole;
use App\Models\Organization;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoleController extends Controller
{
    public function __construct(private EventService $events) {}

    public function index(Organization $organization, Event $event): View
    {
        Gate::authorize('manage', [EventRole::class, $event]);

        $peran = $event->roles()->with('division')->orderBy('id')->get();

        return view('organizer.roles.index', ['org' => $organization, 'event' => $event, 'peran' => $peran]);
    }

    public function create(Organization $organization, Event $event): View
    {
        Gate::authorize('manage', [EventRole::class, $event]);

        $divisi = $event->divisions()->orderBy('name')->get();

        return view('organizer.roles.create', ['org' => $organization, 'event' => $event, 'divisi' => $divisi]);
    }

    public function store(ManageRoleRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();
        $divisi = $event->divisions()->findOrFail($valid['division_id'] ?? 0);

        try {
            $peran = $this->events->createRole($event, $divisi, $valid, $request->user());
        } catch (HttpException $e) {
            return back()->withInput()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.roles.show', [$organization->slug, $event->slug, $peran->id])
            ->with('status', 'Role berhasil dibuat.');
    }

    public function show(Organization $organization, Event $event, EventRole $role): View
    {
        Gate::authorize('manage', $role);

        $role->load(['division', 'shifts']);

        return view('organizer.roles.show', ['org' => $organization, 'event' => $event, 'peran' => $role]);
    }

    public function edit(Organization $organization, Event $event, EventRole $role): View
    {
        Gate::authorize('manage', $role);

        $divisi = $event->divisions()->orderBy('name')->get();

        return view('organizer.roles.edit', ['org' => $organization, 'event' => $event, 'peran' => $role, 'divisi' => $divisi]);
    }

    public function update(ManageRoleRequest $request, Organization $organization, Event $event, EventRole $role): RedirectResponse
    {
        $valid = $request->validated();

        if (array_key_exists('division_id', $valid)) {
            $divisi = $event->divisions()->findOrFail($valid['division_id']);
            $role->forceFill(['division_id' => $divisi->id]);
            unset($valid['division_id']);
        }

        try {
            $this->events->updateRole($role, $valid, $request->user());
        } catch (HttpException $e) {
            return back()->withInput()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.roles.show', [$organization->slug, $event->slug, $role->id])
            ->with('status', 'Role berhasil diperbarui.');
    }

    public function destroy(Organization $organization, Event $event, EventRole $role): RedirectResponse
    {
        Gate::authorize('manage', $role);

        try {
            $this->events->deleteRole($role, request()->user());
        } catch (HttpException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.roles.index', [$organization->slug, $event->slug])
            ->with('status', 'Role berhasil dihapus.');
    }
}

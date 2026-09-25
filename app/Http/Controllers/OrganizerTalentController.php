<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchTalentRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\Organization;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\TalentPoolService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrganizerTalentController extends Controller
{
    public function __construct(
        private TalentPoolService $talentPool,
        private InvitationService $invitation
    ) {}

    public function index(SearchTalentRequest $request, Organization $organization): View
    {
        Gate::authorize('searchTalent', $organization);

        $filters = $request->validated();
        $talents = $this->talentPool->search($organization, $request->user(), $filters);

        $activeEvents = Event::where('organization_id', $organization->id)
            ->whereNotIn('status', ['archived', 'cancelled'])
            ->with('roles')
            ->get();

        return view('organizer.talent.index', [
            'org' => $organization,
            'talents' => $talents,
            'filters' => $filters,
            'activeEvents' => $activeEvents,
        ]);
    }

    public function show(Request $request, Organization $organization, User $user): View
    {
        Gate::authorize('viewTalent', $organization);

        abort_unless($user->volunteerProfile?->visibility !== 'private', 404);

        $participations = $user->registrations()
            ->whereHas('event', fn ($q) => $q->where('organization_id', $organization->id))
            ->whereIn('status', ['accepted', 'completed'])
            ->with(['event', 'role'])
            ->get();

        abort_if($participations->isEmpty(), 404);

        $certificates = $user->certificates()
            ->whereHas('event', fn ($q) => $q->where('organization_id', $organization->id))
            ->with('event')
            ->get();

        $activeEvents = Event::where('organization_id', $organization->id)
            ->whereNotIn('status', ['archived', 'cancelled'])
            ->with('roles')
            ->get();

        return view('organizer.talent.show', [
            'org' => $organization,
            'talent' => $user,
            'participations' => $participations,
            'certificates' => $certificates,
            'activeEvents' => $activeEvents,
        ]);
    }

    public function invite(StoreInvitationRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
        Gate::authorize('inviteTalent', $organization);

        $valid = $request->validated();
        $targetUser = User::findOrFail($valid['user_id']);

        try {
            $this->invitation->invite(
                $event,
                $request->user(),
                $targetUser,
                isset($valid['role_id']) ? (int) $valid['role_id'] : null,
                $valid['message'] ?? null
            );
        } catch (HttpException $e) {
            return back()->withInput()->withErrors(['user_id' => $e->getMessage()]);
        }

        return back()->with('status', 'Undangan berhasil dikirim ke relawan.');
    }

    public function cancel(Request $request, Organization $organization, Event $event, EventInvitation $invitation): RedirectResponse
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
        abort_unless((int) $invitation->event_id === (int) $event->id, 404);
        Gate::authorize('inviteTalent', $organization);

        try {
            $this->invitation->cancel($invitation, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        return back()->with('status', 'Undangan berhasil dibatalkan.');
    }
}

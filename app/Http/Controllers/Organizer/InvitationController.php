<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private MembershipService $members) {}

    public function index(Request $request): View
    {
        $undangan = OrganizationInvitation::with('organization')
            ->where('email', $request->user()->email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->orderByDesc('id')
            ->get();

        return view('invitations.index', ['undangan' => $undangan]);
    }

    public function accept(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        Gate::authorize('accept', $invitation);

        $this->members->acceptInvitation($invitation, $request->user());

        return redirect()->route('invitations.index')
            ->with('status', 'Anda bergabung ke '.$invitation->organization->name.'.');
    }

    public function decline(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        Gate::authorize('decline', $invitation);

        $this->members->declineInvitation($invitation, $request->user());

        return redirect()->route('invitations.index')
            ->with('status', 'Undangan ditolak.');
    }
}

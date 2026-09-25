<?php

namespace App\Http\Controllers;

use App\Http\Requests\RespondInvitationRequest;
use App\Models\EventInvitation;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VolunteerInvitationController extends Controller
{
    public function __construct(private InvitationService $invitation) {}

    public function index(Request $request): View
    {
        $invitations = $request->user()->eventInvitations()
            ->with(['event.organization', 'role', 'invitedBy'])
            ->orderByDesc('id')
            ->paginate(15);

        return view('my.invitations.index', [
            'invitations' => $invitations,
        ]);
    }

    public function respond(RespondInvitationRequest $request, EventInvitation $invitation): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $this->invitation->respond($invitation, $request->user(), $valid['action']);
        } catch (HttpException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        $pesan = $valid['action'] === 'accepted' ? 'Undangan berhasil diterima.' : 'Undangan ditolak.';

        return back()->with('status', $pesan);
    }
}

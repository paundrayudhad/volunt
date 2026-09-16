<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function __construct(private MembershipService $members) {}

    public function index(Organization $organization): View
    {
        Gate::authorize('viewAny', [OrganizationMember::class, $organization]);

        $anggota = $organization->members()->with('user')->orderBy('id')->get();
        $undangan = $organization->invitations()->orderByDesc('id')->limit(20)->get();

        return view('organizer.members.index', ['org' => $organization, 'anggota' => $anggota, 'undangan' => $undangan]);
    }

    public function create(Organization $organization): View
    {
        Gate::authorize('invite', [OrganizationMember::class, $organization]);

        return view('organizer.members.create', ['org' => $organization]);
    }

    public function store(InviteMemberRequest $request, Organization $organization): RedirectResponse
    {
        $valid = $request->validated();
        $valid['role'] = 'staff';

        try {
            $this->members->invite($organization, $valid, $request->user());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()->route('organizer.members.index', $organization->slug)
            ->with('status', 'Undangan terkirim ke '.$valid['email'].'.');
    }

    public function update(UpdateMemberRequest $request, Organization $organization, OrganizationMember $member): RedirectResponse
    {
        abort_unless($member->organization_id === $organization->id, 404);

        $this->members->changeRole($member, $request->validated()['role'], $request->user());

        return redirect()->route('organizer.members.index', $organization->slug)
            ->with('status', 'Role anggota diperbarui.');
    }

    public function destroy(Organization $organization, OrganizationMember $member): RedirectResponse
    {
        abort_unless($member->organization_id === $organization->id, 404);
        Gate::authorize('remove', $member);

        $this->members->removeMember($member, request()->user());

        return redirect()->route('organizer.members.index', $organization->slug)
            ->with('status', 'Anggota dikeluarkan.');
    }
}

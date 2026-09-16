<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\MembershipService;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationService $organizations,
        private MembershipService $memberships,
    ) {}

    public function show(Organization $organization): View
    {
        Gate::authorize('view', $organization);

        return view('organizer.organizations.show', ['org' => $organization]);
    }

    public function edit(Organization $organization): View
    {
        Gate::authorize('update', $organization);

        return view('organizer.organizations.edit', ['org' => $organization]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->organizations->updateProfile($organization, $request->validated(), $request->user());

        return redirect()->route('organizer.show', $organization->slug)
            ->with('status', 'Profil organisasi diperbarui.');
    }

    public function transfer(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('transfer', $organization);

        $data = $request->validate([
            'member_id' => ['required', 'integer'],
        ]);

        $member = $organization->members()->whereKey($data['member_id'])->firstOrFail();
        $this->memberships->transferOwnership($organization, $member->user, $request->user());

        return redirect()->route('organizer.show', $organization->slug)
            ->with('status', 'Kepemilikan organisasi dialihkan.');
    }
}

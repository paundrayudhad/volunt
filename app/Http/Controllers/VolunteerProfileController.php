<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateVolunteerProfileRequest;
use App\Models\VolunteerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VolunteerProfileController extends Controller
{
    public function edit(): View
    {
        $profile = request()->user()->volunteerProfile()->first()
            ?? new VolunteerProfile(['visibility' => 'organizers_only']);

        return view('profile.volunteer', ['profile' => $profile]);
    }

    public function update(UpdateVolunteerProfileRequest $request): RedirectResponse
    {
        $valid = $request->validated();

        request()->user()->volunteerProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            array_intersect_key($valid, array_flip((new VolunteerProfile)->getFillable()))
        );

        return redirect()->route('profile.volunteer.edit')
            ->with('status', 'Profil volunteer berhasil disimpan.');
    }
}

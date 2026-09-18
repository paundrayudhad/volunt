<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkReviewRequest;
use App\Http\Requests\ReviewRegistrationRequest;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegistrationController extends Controller
{
    public function __construct(private RegistrationService $registrations) {}

    public function index(Request $request, Organization $organization, Event $event): View
    {
        Gate::authorize('viewAny', [Registration::class, $event]);

        $status = $request->query('status');

        $items = Registration::where('event_id', $event->id)
            ->with(['user', 'role'])
            ->when(
                in_array($status, ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted', 'cancelled', 'withdrawn'], true),
                fn ($query) => $query->where('status', $status)
            )
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('organizer.events.registrations.index', [
            'org' => $organization,
            'event' => $event,
            'registrations' => $items,
            'selectedStatus' => in_array($status, ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted', 'cancelled', 'withdrawn'], true) ? $status : '',
            'statusOptions' => ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted', 'cancelled', 'withdrawn'],
        ]);
    }

    public function show(Organization $organization, Event $event, Registration $registration): View
    {
        Gate::authorize('view', $registration);

        $registration->load(['user', 'role', 'answers.field', 'histories']);

        return view('organizer.events.registrations.show', [
            'org' => $organization,
            'event' => $event,
            'registration' => $registration,
        ]);
    }

    public function review(ReviewRegistrationRequest $request, Organization $organization, Event $event, Registration $registration): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $this->registrations->review($registration, $valid['action'], $request->user(), $valid['reason'] ?? null);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.registrations.show', [$organization->slug, $event->slug, $registration->id])
            ->with('status', 'Status pendaftaran diubah menjadi '.$valid['action'].'.');
    }

    public function bulkReview(BulkReviewRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $this->registrations->bulkReview($event, array_map('intval', $valid['ids']), $valid['action'], $request->user(), $valid['reason'] ?? null);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['ids' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.registrations.index', [$organization->slug, $event->slug])
            ->with('status', 'Seleksi massal berhasil diterapkan.');
    }
}

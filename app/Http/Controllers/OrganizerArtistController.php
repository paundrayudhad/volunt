<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignLiaisonRequest;
use App\Http\Requests\StoreArtistNoteRequest;
use App\Http\Requests\StoreArtistRequest;
use App\Http\Requests\ToggleRiderRequest;
use App\Http\Requests\TransitionArtistRequest;
use App\Http\Requests\UpdateArtistRequest;
use App\Models\Artist;
use App\Models\ArtistLiaison;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\ArtistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrganizerArtistController extends Controller
{
    public function __construct(private ArtistService $artis) {}

    public function index(Request $request, Organization $organization, Event $event): View
    {
        Gate::authorize('viewAny', [Artist::class, $event]);

        $items = Artist::where('event_id', $event->id)
            ->with(['liaisons.user'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('attendance'), fn ($query, $kehadiran) => $query->where('attendance', $kehadiran))
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('performance_order')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('organizer.artists.index', [
            'org' => $organization,
            'event' => $event,
            'artists' => $items,
        ]);
    }

    public function show(Organization $organization, Event $event, Artist $artis): View
    {
        Gate::authorize('view', $artis);

        $artis->load(['liaisons.user', 'notes.author', 'histories.actor']);

        $volunteers = Registration::where('event_id', $event->id)
            ->where('status', 'accepted')
            ->with('user')
            ->get()
            ->map(fn (Registration $registrasi) => $registrasi->user)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('organizer.artists.show', [
            'org' => $organization,
            'event' => $event,
            'artist' => $artis,
            'volunteers' => $volunteers,
        ]);
    }

    public function store(StoreArtistRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $artis = $this->artis->create($event, $request->user(), $valid);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.artists.show', [$organization->slug, $event->slug, $artis->id])
            ->with('status', 'Artis ditambahkan.');
    }

    public function update(UpdateArtistRequest $request, Organization $organization, Event $event, Artist $artis): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $this->artis->update($artis, $request->user(), $valid);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.artists.show', [$organization->slug, $event->slug, $artis->id])
            ->with('status', 'Data artis diperbarui.');
    }

    public function transition(TransitionArtistRequest $request, Organization $organization, Event $event, Artist $artis): RedirectResponse
    {
        $valid = $request->validated();

        try {
            if ($valid['to'] === 'cancelled') {
                $this->artis->cancel($artis, $request->user(), (string) ($valid['note'] ?? ''));
            } else {
                $this->artis->transition($artis, $request->user(), $valid['to'], $valid['note'] ?? null);
            }
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['to' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.artists.show', [$organization->slug, $event->slug, $artis->id])
            ->with('status', 'Status diperbarui.');
    }

    public function assign(AssignLiaisonRequest $request, Organization $organization, Event $event, Artist $artis): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $lo = User::whereKey($valid['user_id'])->firstOrFail();
            $this->artis->assignLiaison($artis, $request->user(), $lo);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['user_id' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.artists.show', [$organization->slug, $event->slug, $artis->id])
            ->with('status', 'LO berhasil ditugaskan.');
    }

    public function release(Request $request, Organization $organization, Event $event, Artist $artis, ArtistLiaison $liaison): RedirectResponse
    {
        abort_unless($liaison->artist !== null, 404);
        Gate::authorize('manage', $artis);

        $artisId = $artis->id;
        $this->artis->releaseLiaison($liaison, $request->user());

        return redirect()->route('organizer.events.artists.show', [$organization->slug, $event->slug, $artisId])
            ->with('status', 'LO dilepas dari artis.');
    }

    public function note(StoreArtistNoteRequest $request, Organization $organization, Event $event, Artist $artis): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $this->artis->addNote($artis, $request->user(), $valid['body']);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['body' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.artists.show', [$organization->slug, $event->slug, $artis->id])
            ->with('status', 'Catatan disimpan.');
    }

    public function rider(ToggleRiderRequest $request, Organization $organization, Event $event, Artist $artis): RedirectResponse
    {
        $valid = $request->validated();

        $this->artis->toggleRider($artis, $request->user(), (bool) $valid['fulfilled']);

        return redirect()->route('organizer.events.artists.show', [$organization->slug, $event->slug, $artis->id])
            ->with('status', 'Status rider diperbarui.');
    }

    public function destroy(Organization $organization, Event $event, Artist $artis): RedirectResponse
    {
        Gate::authorize('manage', $artis);

        $this->artis->destroy($artis, request()->user());

        return redirect()->route('organizer.events.artists.index', [$organization->slug, $event->slug])
            ->with('status', 'Artis diarsipkan.');
    }
}

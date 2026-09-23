<?php

namespace App\Http\Controllers;

use App\Http\Requests\LiaisonStatusRequest;
use App\Http\Requests\StoreArtistNoteRequest;
use App\Http\Requests\ToggleRiderRequest;
use App\Models\Artist;
use App\Models\ArtistLiaison;
use App\Services\ArtistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VolunteerLiaisonController extends Controller
{
    public function __construct(private ArtistService $artis) {}

    public function index(): View
    {
        $items = Artist::whereIn('id', ArtistLiaison::where('user_id', request()->user()->id)->pluck('artist_id'))
            ->with(['event', 'event.organization'])
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('performance_order')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('my.liaison.index', [
            'artists' => $items,
        ]);
    }

    public function show(): View
    {
        $artis = $this->milikSaya();

        $artis->load(['event', 'liaisons.user', 'notes.author', 'histories.actor']);

        return view('my.liaison.show', [
            'artist' => $artis,
        ]);
    }

    public function status(LiaisonStatusRequest $request): RedirectResponse
    {
        $artis = $this->milikSaya();
        $valid = $request->validated();

        try {
            if (($valid['to'] ?? null) === 'cancelled') {
                $this->artis->cancel($artis, $request->user(), (string) ($valid['note'] ?? ''));
            } elseif (isset($valid['to'])) {
                $this->artis->transition($artis, $request->user(), $valid['to'], $valid['note'] ?? null);
            }

            if (isset($valid['attendance'])) {
                $this->artis->markAttendance($artis->refresh(), $request->user(), $valid['attendance']);
            }
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['to' => $e->getMessage()]);
        }

        return redirect()->route('my.liaison.show', [$artis->id])
            ->with('status', 'Status diperbarui.');
    }

    public function note(StoreArtistNoteRequest $request): RedirectResponse
    {
        $artis = $this->milikSaya();
        $valid = $request->validated();

        try {
            $this->artis->addNote($artis, $request->user(), $valid['body']);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['body' => $e->getMessage()]);
        }

        return redirect()->route('my.liaison.show', [$artis->id])
            ->with('status', 'Catatan disimpan.');
    }

    public function rider(ToggleRiderRequest $request): RedirectResponse
    {
        $artis = $this->milikSaya();
        $valid = $request->validated();

        $this->artis->toggleRider($artis, $request->user(), (bool) $valid['fulfilled']);

        return redirect()->route('my.liaison.show', [$artis->id])
            ->with('status', 'Status rider diperbarui.');
    }

    private function milikSaya(): Artist
    {
        $artis = request()->route()?->parameter('artist');
        abort_unless($artis instanceof Artist, 404);

        // Own-scoped: bukan LO-nya → 404 (bukan 403 policy).
        abort_unless(ArtistLiaison::where('artist_id', $artis->id)
            ->where('user_id', request()->user()->id)
            ->exists(), 404);

        return $artis;
    }
}

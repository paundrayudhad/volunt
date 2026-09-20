<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Incident;
use App\Models\Registration;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IncidentController extends Controller
{
    public function __construct(private IncidentService $insiden) {}

    public function index(): View
    {
        $items = Incident::where('reporter_id', request()->user()->id)
            ->with(['event', 'event.organization'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $events = Event::whereIn('id', Registration::where('user_id', request()->user()->id)
            ->where('status', 'accepted')
            ->pluck('event_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('volunteer.incidents.index', [
            'incidents' => $items,
            'events' => $events,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $valid = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'category' => ['required', Rule::in(Incident::CATEGORIES)],
            'priority' => ['nullable', Rule::in(Incident::PRIORITIES)],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
        ], [
            'event_id.required' => 'Event wajib dipilih.',
            'event_id.exists' => 'Event tidak ditemukan.',
            'category.required' => 'Kategori insiden wajib diisi.',
            'category.in' => 'Kategori insiden tidak dikenal.',
            'priority.in' => 'Prioritas insiden tidak dikenal.',
            'location.required' => 'Lokasi insiden wajib diisi.',
            'description.required' => 'Deskripsi insiden wajib diisi.',
            'description.min' => 'Deskripsi insiden minimal 10 karakter.',
        ]);

        $event = Event::whereKey($valid['event_id'])->firstOrFail();
        abort_unless($this->diikuti($event->id, $request->user()->id), 404);

        try {
            $this->insiden->report($event, $request->user(), $valid);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['description' => $e->getMessage()]);
        }

        return redirect()->route('my.incidents.index')
            ->with('status', 'Laporan insiden terkirim.');
    }

    private function diikuti(int $eventId, int $userId): bool
    {
        return Registration::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->exists();
    }
}

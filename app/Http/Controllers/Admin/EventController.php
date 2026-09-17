<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventController extends Controller
{
    private const STATUS = [
        'draft',
        'published',
        'registration_open',
        'registration_closed',
        'ongoing',
        'completed',
        'archived',
        'cancelled',
    ];

    public function __construct(private EventService $events) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $orgId = $request->query('org');

        $acara = Event::with('organization')
            ->when(in_array($status, self::STATUS, true), fn ($query) => $query->where('status', $status))
            ->when(is_numeric($orgId), fn ($query) => $query->where('organization_id', (int) $orgId))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.events.index', [
            'acara' => $acara,
            'statusDipilih' => in_array($status, self::STATUS, true) ? $status : '',
            'orgDipilih' => is_numeric($orgId) ? (int) $orgId : '',
            'daftarStatus' => self::STATUS,
        ]);
    }

    public function cancel(Request $request, int $eventAdmin): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $event = Event::findOrFail($eventAdmin);

        try {
            $this->events->transitionTo($event, 'cancelled', $request->user(), $data['reason']);
        } catch (HttpException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()->route('admin.events.index')
            ->with('status', 'Event dibatalkan paksa.');
    }
}

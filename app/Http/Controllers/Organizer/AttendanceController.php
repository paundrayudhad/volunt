<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManualAttendanceRequest;
use App\Http\Requests\ScanAttendanceRequest;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Organization;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $kehadiran) {}

    public function index(Request $request, Organization $organization, Event $event): View
    {
        abort_unless(
            $request->user()->belongsToOrganization($organization->id)
            && $request->user()->can('attendance.read'),
            403
        );

        $items = Attendance::where('event_id', $event->id)
            ->with(['user', 'assignment', 'shift'])
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('organizer.events.attendances.index', [
            'org' => $organization,
            'event' => $event,
            'attendances' => $items,
        ]);
    }

    public function scan(Organization $organization, Event $event): View
    {
        abort_unless(
            request()->user()->belongsToOrganization($organization->id)
            && request()->user()->can('attendance.record'),
            403
        );

        return view('organizer.events.attendances.scan', [
            'org' => $organization,
            'event' => $event,
        ]);
    }

    public function process(ScanAttendanceRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        try {
            if ($valid['action'] === 'check_out') {
                $this->kehadiran->checkOut($valid['token'], $request->user(), $valid['idempotency_key'], (int) $event->id);
            } else {
                $this->kehadiran->checkIn($valid['token'], $request->user(), $valid['idempotency_key'], (int) $event->id);
            }
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404 || $request->expectsJson()) {
                throw $e;
            }

            return back()->withInput()->withErrors(['token' => $e->getMessage()]);
        }

        $pesan = $valid['action'] === 'check_out' ? 'Check-out berhasil dicatat.' : 'Check-in berhasil dicatat.';

        return redirect()->route('organizer.events.attendances.index', [$organization->slug, $event->slug])
            ->with('status', $pesan);
    }

    public function manual(ManualAttendanceRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        $assignment = Assignment::whereKey($valid['assignment_id'])
            ->where('event_id', $event->id)
            ->first();
        abort_if($assignment === null, 404);

        try {
            $this->kehadiran->manual($assignment, $request->user(), $valid['reason'], $valid['idempotency_key']);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404 || $request->expectsJson()) {
                throw $e;
            }

            return back()->withInput()->withErrors(['assignment_id' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.attendances.index', [$organization->slug, $event->slug])
            ->with('status', 'Kehadiran manual berhasil dicatat.');
    }
}

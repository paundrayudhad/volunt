<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(): View
    {
        $tugas = request()->user()->assignments()
            ->with(['event', 'division', 'role', 'shift', 'attendances'])
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('registrations.schedule', ['assignments' => $tugas]);
    }
}

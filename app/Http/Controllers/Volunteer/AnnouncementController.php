<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private AnnouncementService $pengumuman) {}

    public function index(Request $request): View
    {
        return view('announcements.index', [
            'announcements' => $this->pengumuman->visibleFor($request->user()),
        ]);
    }
}

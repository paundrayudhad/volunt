<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublishAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Organization;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private AnnouncementService $pengumuman) {}

    public function index(Organization $organization, Event $event): View
    {
        Gate::authorize('viewAny', [Announcement::class, $event]);

        $items = Announcement::where('event_id', $event->id)
            ->with('author')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('organizer.events.announcements.index', [
            'org' => $organization,
            'event' => $event,
            'announcements' => $items,
        ]);
    }

    public function create(Organization $organization, Event $event): View
    {
        Gate::authorize('publish', [Announcement::class, $event]);

        return view('organizer.events.announcements.create', [
            'org' => $organization,
            'event' => $event,
            'divisions' => $event->divisions()->orderBy('name')->get(),
            'roles' => $event->roles()->orderBy('name')->get(),
            'shifts' => $event->shifts()->orderBy('start_at')->get(),
        ]);
    }

    public function store(PublishAnnouncementRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        $pengumuman = Announcement::unguarded(fn (): Announcement => Announcement::create([
            'event_id' => $event->id,
            'author_id' => $request->user()->id,
            'target_type' => $valid['target_type'],
            'target_id' => $valid['target_id'] ?? null,
            'title' => $valid['title'],
            'body' => $valid['body'],
            'expires_at' => $valid['expires_at'] ?? null,
        ]));

        return redirect()->route('organizer.events.announcements.show', [$organization->slug, $event->slug, $pengumuman->id])
            ->with('status', 'Draf pengumuman berhasil dibuat.');
    }

    public function show(Organization $organization, Event $event, Announcement $pengumuman): View
    {
        Gate::authorize('view', $pengumuman);

        $pengumuman->load(['author', 'event']);

        return view('organizer.events.announcements.show', [
            'org' => $organization,
            'event' => $event,
            'announcement' => $pengumuman,
            'recipientCount' => $this->pengumuman->recipients($pengumuman)->count(),
        ]);
    }

    public function publish(Organization $organization, Event $event, Announcement $pengumuman): RedirectResponse
    {
        Gate::authorize('publish', $pengumuman);

        $this->pengumuman->publish($pengumuman, request()->user());

        return redirect()->route('organizer.events.announcements.show', [$organization->slug, $event->slug, $pengumuman->id])
            ->with('status', 'Pengumuman berhasil diterbitkan.');
    }
}

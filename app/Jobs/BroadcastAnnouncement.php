<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Notifications\EventAnnouncement;
use App\Services\AnnouncementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class BroadcastAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $announcementId) {}

    public function handle(AnnouncementService $pengumuman): void
    {
        $item = Announcement::whereKey($this->announcementId)->first();

        if ($item === null || ! $item->isPublished()) {
            return;
        }

        $penerima = $pengumuman->recipients($item);

        if ($penerima->isNotEmpty()) {
            Notification::send($penerima, new EventAnnouncement($item));
        }
    }
}

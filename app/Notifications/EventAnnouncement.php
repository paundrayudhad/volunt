<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventAnnouncement extends Notification
{
    use Queueable;

    public function __construct(public Announcement $pengumuman) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'announcement_id' => $this->pengumuman->id,
            'event_id' => $this->pengumuman->event_id,
            'title' => $this->pengumuman->title,
            'target_type' => $this->pengumuman->target_type,
        ];
    }
}

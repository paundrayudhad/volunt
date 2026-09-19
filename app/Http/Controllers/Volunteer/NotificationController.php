<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $items = $request->user()->notifications()->orderByDesc('created_at')->paginate(15)->withQueryString();

        return view('notifications.index', [
            'notifications' => $items,
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notif = DatabaseNotification::whereKey($id)
            ->where('notifiable_type', $request->user()::class)
            ->where('notifiable_id', $request->user()->id)
            ->first();
        abort_if($notif === null, 404);

        $notif->markAsRead();

        return redirect()->route('notifications.index')
            ->with('status', 'Notifikasi ditandai sudah dibaca.');
    }
}

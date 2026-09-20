<?php

namespace App\Http\Controllers;

use App\Models\LostFoundItem;
use App\Models\Registration;
use App\Services\StoredPhoto;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class LostFoundPhotoController extends Controller
{
    public function show(LostFoundItem $lostFoundItem): Response
    {
        abort_unless($lostFoundItem->photo_path !== null, 404);
        $lostFoundItem->loadMissing('event');
        $user = auth()->user();
        $boleh = $user !== null
            && ($user->belongsToOrganization($lostFoundItem->event->organization_id)
                || Registration::where('event_id', $lostFoundItem->event_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'accepted')
                    ->exists());
        abort_unless($boleh, 404);
        abort_unless(Storage::disk('local')->exists($lostFoundItem->photo_path), 404);

        return response(
            Storage::disk('local')->get($lostFoundItem->photo_path),
            200,
            [
                'Content-Type' => StoredPhoto::mimeFor($lostFoundItem->photo_path),
                'Content-Disposition' => 'inline',
            ]
        );
    }
}

<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\LostFoundItem;
use App\Models\Registration;
use App\Services\LostFoundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LostFoundController extends Controller
{
    public function __construct(private LostFoundService $temuan) {}

    public function index(): View
    {
        $userId = request()->user()->id;
        $eventIds = Registration::where('user_id', $userId)
            ->where('status', 'accepted')
            ->pluck('event_id');

        $milikku = LostFoundItem::where('reporter_id', $userId)
            ->with(['event'])
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'milikku')
            ->withQueryString();

        $temu = LostFoundItem::where('status', 'found')
            ->whereIn('event_id', $eventIds)
            ->with(['event'])
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'temu')
            ->withQueryString();

        $events = Event::whereIn('id', $eventIds)->orderBy('name')->get(['id', 'name']);

        return view('volunteer.lost-found.index', [
            'mine' => $milikku,
            'found' => $temu,
            'events' => $events,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $valid = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'kind' => ['required', Rule::in(LostFoundItem::KINDS)],
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'location' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ], [
            'event_id.required' => 'Event wajib dipilih.',
            'event_id.exists' => 'Event tidak ditemukan.',
            'kind.required' => 'Jenis laporan wajib diisi.',
            'kind.in' => 'Jenis laporan tidak dikenal.',
            'item_name.required' => 'Nama barang wajib diisi.',
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Format foto: jpg, jpeg, png, atau webp.',
            'photo.max' => 'Ukuran foto maksimal 5MB.',
        ]);

        $event = Event::whereKey($valid['event_id'])->firstOrFail();
        abort_unless($this->diikuti($event->id, $request->user()->id), 404);

        try {
            $this->temuan->report($event, $request->user(), array_merge($valid, [
                'photo' => $request->file('photo'),
            ]));
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['item_name' => $e->getMessage()]);
        }

        return redirect()->route('my.lost_found.index')
            ->with('status', 'Laporan barang terkirim.');
    }

    public function claim(Request $request, LostFoundItem $lostFoundItem): RedirectResponse
    {
        abort_unless($this->diikuti($lostFoundItem->event_id, $request->user()->id), 404);

        try {
            $this->temuan->claim($lostFoundItem, $request->user());
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return redirect()->route('my.lost_found.index')
                ->withErrors(['claim' => $e->getMessage()]);
        }

        return redirect()->route('my.lost_found.index')
            ->with('status', 'Klaim tercatat, hubungi posko untuk verifikasi.');
    }

    private function diikuti(int $eventId, int $userId): bool
    {
        return Registration::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->exists();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicEventController extends Controller
{
    public function index(Request $request): View
    {
        $pencarian = trim((string) $request->query('search', ''));
        $kategori = trim((string) $request->query('kategori', ''));
        $kota = trim((string) $request->query('kota', ''));

        $acara = Event::published()
            ->when($pencarian !== '', fn ($query) => $query->where('name', 'like', '%'.$pencarian.'%'))
            ->when($kategori !== '', fn ($query) => $query->where('category', $kategori))
            ->when($kota !== '', fn ($query) => $query->where(
                fn ($sub) => $sub->where('venue', 'like', '%'.$kota.'%')
                    ->orWhere('address', 'like', '%'.$kota.'%')
            ))
            ->orderBy('start_at')
            ->paginate(12)
            ->withQueryString();

        return view('events.index', [
            'acara' => $acara,
            'pencarian' => $pencarian,
            'kategori' => $kategori,
            'kota' => $kota,
        ]);
    }

    public function show(Event $eventPublic): View
    {
        $eventPublic->load([
            'organization',
            'divisions.roles',
            'roles.division',
            'roles' => fn ($query) => $query->orderBy('name'),
            'shifts' => fn ($query) => $query->orderBy('start_at'),
        ]);

        return view('events.show', ['event' => $eventPublic]);
    }
}

<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveClaimRequest;
use App\Http\Requests\StoreLostFoundRequest;
use App\Models\Event;
use App\Models\LostFoundItem;
use App\Models\Organization;
use App\Services\AuditLogService;
use App\Services\LostFoundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LostFoundController extends Controller
{
    public function __construct(private LostFoundService $temuan, private AuditLogService $audit) {}

    public function index(Request $request, Organization $organization, Event $event): View
    {
        Gate::authorize('viewAny', [LostFoundItem::class, $event]);

        $items = LostFoundItem::where('event_id', $event->id)
            ->with(['reporter', 'claimant'])
            ->when($request->query('kind'), fn ($query, $jenis) => $query->where('kind', $jenis))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('organizer.events.lost-found.index', [
            'org' => $organization,
            'event' => $event,
            'items' => $items,
        ]);
    }

    public function show(Organization $organization, Event $event, LostFoundItem $lostFoundItem): View
    {
        Gate::authorize('view', $lostFoundItem);

        $lostFoundItem->load(['reporter', 'claimant', 'handler', 'incidents']);

        return view('organizer.events.lost-found.show', [
            'org' => $organization,
            'event' => $event,
            'item' => $lostFoundItem,
        ]);
    }

    public function store(StoreLostFoundRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $item = $this->temuan->report($event, $request->user(), array_merge($valid, [
                'photo' => $request->file('photo'),
            ]));
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['item_name' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.lost_found.show', [$organization->slug, $event->slug, $item->id])
            ->with('status', 'Laporan barang berhasil disimpan.');
    }

    public function resolveClaim(ResolveClaimRequest $request, Organization $organization, Event $event, LostFoundItem $lostFoundItem): RedirectResponse
    {
        $valid = $request->validated();

        try {
            $this->temuan->resolveClaim($lostFoundItem, $request->user(), $valid['decision'], $valid['note'] ?? null);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withInput()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.lost_found.show', [$organization->slug, $event->slug, $lostFoundItem->id])
            ->with('status', 'Keputusan klaim berhasil disimpan.');
    }

    public function close(Organization $organization, Event $event, LostFoundItem $lostFoundItem): RedirectResponse
    {
        Gate::authorize('manage', $lostFoundItem);

        try {
            $this->temuan->close($lostFoundItem, request()->user());
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                throw $e;
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.lost_found.show', [$organization->slug, $event->slug, $lostFoundItem->id])
            ->with('status', 'Laporan barang berhasil ditutup.');
    }

    public function destroy(Organization $organization, Event $event, LostFoundItem $lostFoundItem): RedirectResponse
    {
        Gate::authorize('manage', $lostFoundItem);

        $lostFoundItem->delete();
        $this->audit->record(request()->user(), 'lostfound.deleted', LostFoundItem::class, $lostFoundItem->id, [
            'event_id' => $event->id,
        ]);

        return redirect()->route('organizer.events.lost_found.index', [$organization->slug, $event->slug])
            ->with('status', 'Laporan barang diarsipkan.');
    }
}

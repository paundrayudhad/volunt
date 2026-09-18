<?php

namespace App\Services;

use App\Jobs\BroadcastAnnouncement;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnnouncementService
{
    public function __construct(private AuditLogService $audit) {}

    /**
     * @return Collection<int, User>
     */
    public function recipients(Announcement $pengumuman): Collection
    {
        $eventId = (int) $pengumuman->event_id;

        $diterima = Registration::where('event_id', $eventId)
            ->where('status', 'accepted');

        return match ($pengumuman->target_type) {
            'division' => $this->penerimaAssignment(
                $eventId,
                fn ($query) => $query->where('assignments.division_id', (int) $pengumuman->target_id)
            ),
            'role' => $this->penerimaAssignment(
                $eventId,
                fn ($query) => $query->where('assignments.role_id', (int) $pengumuman->target_id)
            ),
            'shift' => $this->penerimaAssignment(
                $eventId,
                fn ($query) => $query->where('assignments.shift_id', (int) $pengumuman->target_id)
            ),
            'individual' => $diterima->where('user_id', (int) $pengumuman->target_id)->with('user')->get()->pluck('user')->filter(),
            default => $diterima->with('user')->get()->pluck('user')->filter(),
        };
    }

    public function publish(Announcement $pengumuman, User $aktor): Announcement
    {
        return DB::transaction(function () use ($pengumuman, $aktor): Announcement {
            $pengumuman = $pengumuman->refresh();
            $this->validasiTarget($pengumuman);

            $pengumuman->forceFill(['published_at' => now()])->save();

            $event = $pengumuman->event()->firstOrFail();
            $this->audit->record($aktor, 'announcement.publish', Announcement::class, $pengumuman->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);

            BroadcastAnnouncement::dispatch($pengumuman->id);

            return $pengumuman->refresh();
        });
    }

    /**
     * Pengumuman yang boleh dilihat relawan: terbit, belum kedaluwarsa, dan relevan untuknya.
     *
     * @return Collection<int, Announcement>
     */
    public function visibleFor(User $relawan): Collection
    {
        $eventIds = Registration::where('user_id', $relawan->id)
            ->where('status', 'accepted')
            ->pluck('event_id')
            ->unique()
            ->values();

        if ($eventIds->isEmpty()) {
            return collect();
        }

        $items = Announcement::whereIn('event_id', $eventIds)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $penugasan = Assignment::where('user_id', $relawan->id)
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', Assignment::ACTIVE)
            ->get()
            ->groupBy('event_id');

        return $items->filter(fn (Announcement $item): bool => $this->relevanUntuk($item, $relawan, $penugasan->get($item->event_id, collect())))->values();
    }

    public function relevanUntuk(Announcement $item, User $relawan, Collection $penugasanEvent): bool
    {
        return match ($item->target_type) {
            'division' => $penugasanEvent->contains(fn (Assignment $tugas): bool => (int) $tugas->division_id === (int) $item->target_id),
            'role' => $penugasanEvent->contains(fn (Assignment $tugas): bool => (int) $tugas->role_id === (int) $item->target_id),
            'shift' => $penugasanEvent->contains(fn (Assignment $tugas): bool => (int) $tugas->shift_id === (int) $item->target_id),
            'individual' => (int) $item->target_id === (int) $relawan->id,
            default => true,
        };
    }

    private function validasiTarget(Announcement $pengumuman): void
    {
        $eventId = (int) $pengumuman->event_id;

        match ($pengumuman->target_type) {
            'division' => abort_unless(
                $pengumuman->event->divisions()->whereKey((int) $pengumuman->target_id)->exists(),
                422,
                'Divisi target tidak termasuk event ini.'
            ),
            'role' => abort_unless(
                $pengumuman->event->roles()->whereKey((int) $pengumuman->target_id)->exists(),
                422,
                'Peran target tidak termasuk event ini.'
            ),
            'shift' => abort_unless(
                $pengumuman->event->shifts()->whereKey((int) $pengumuman->target_id)->exists(),
                422,
                'Shift target tidak termasuk event ini.'
            ),
            'individual' => abort_unless(
                Registration::where('event_id', $eventId)
                    ->where('user_id', (int) $pengumuman->target_id)
                    ->where('status', 'accepted')
                    ->exists(),
                422,
                'Pengguna target belum diterima di event ini.'
            ),
            default => abort_unless($pengumuman->target_type === 'event', 422, 'Target pengumuman tidak valid.'),
        };
    }

    /**
     * @param  callable(\Illuminate\Database\Eloquent\Builder<Assignment>): void  $filter
     * @return Collection<int, User>
     */
    private function penerimaAssignment(int $eventId, callable $filter): Collection
    {
        $query = Assignment::where('event_id', $eventId)
            ->whereIn('status', Assignment::ACTIVE)
            ->with('user');
        $filter($query);

        return $query->get()->pluck('user')->filter()->unique('id')->values();
    }
}

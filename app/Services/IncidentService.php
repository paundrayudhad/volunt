<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Models\LostFoundItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    public function __construct(private AuditLogService $audit) {}

    /** @param array{category: string, priority?: string, location: string, description: string, lost_found_item_id?: int} $data */
    public function report(Event $event, User $pelapor, array $data): Incident
    {
        abort_unless(in_array($data['category'], Incident::CATEGORIES, true), 422, 'Kategori insiden tidak dikenal.');
        $prioritas = $data['priority'] ?? 'medium';
        abort_unless(in_array($prioritas, Incident::PRIORITIES, true), 422, 'Prioritas insiden tidak dikenal.');
        $tautanId = $data['lost_found_item_id'] ?? null;

        return DB::transaction(function () use ($event, $pelapor, $data, $prioritas, $tautanId): Incident {
            if ($tautanId !== null) {
                $tautan = LostFoundItem::whereKey($tautanId)->lockForUpdate()->first();
                abort_unless($tautan !== null && (int) $tautan->event_id === (int) $event->id, 422, 'Item tertaut bukan milik event ini.');
            }
            $insiden = Incident::unguarded(fn (): Incident => Incident::create([
                'event_id' => $event->id,
                'category' => $data['category'],
                'priority' => $prioritas,
                'location' => $data['location'],
                'description' => $data['description'],
                'reporter_id' => $pelapor->id,
                'status' => 'open',
                'lost_found_item_id' => $tautanId,
            ]));
            $this->audit->record($pelapor, 'incident.reported', Incident::class, $insiden->id, [
                'event_id' => $event->id, 'new' => ['category' => $insiden->category, 'priority' => $insiden->priority],
            ]);

            return $insiden;
        });
    }

    public function assign(Incident $insiden, User $actor, User $tugas): Incident
    {
        abort_unless($tugas->belongsToOrganization($insiden->event->organization_id), 422, 'Petugas harus member organisasi yang sama.');
        abort_unless($insiden->status === 'open', 422, 'Hanya insiden open yang dapat ditugaskan.');

        return $this->pindah($insiden, $actor, 'assigned', ['assignee_id' => $tugas->id], 'incident.assigned');
    }

    public function transition(Incident $insiden, User $actor, string $tujuan, ?string $catatan = null): Incident
    {
        $sah = Incident::NEXT[$insiden->status] ?? [];
        abort_unless(in_array($tujuan, $sah, true), 422, 'Tahap berikutnya yang sah: '.($sah === [] ? 'tidak ada.' : implode(', ', $sah).'.'));

        return $this->pindah($insiden, $actor, $tujuan, [], 'incident.transitioned', $catatan);
    }

    public function reopen(Incident $insiden, User $actor, string $alasan): Incident
    {
        abort_unless(in_array($insiden->status, ['resolved', 'closed'], true), 422, 'Hanya insiden resolved/closed yang dapat dibuka ulang.');
        abort_unless(mb_strlen(trim($alasan)) >= 10, 422, 'Alasan pembukaan ulang minimal 10 karakter.');

        return $this->pindah($insiden, $actor, 'open', ['assignee_id' => null], 'incident.reopened', $alasan);
    }

    /** @param array<string, mixed> $tambahan */
    private function pindah(Incident $insiden, User $actor, string $tujuan, array $tambahan, string $aksi, ?string $catatan = null): Incident
    {
        return DB::transaction(function () use ($insiden, $actor, $tujuan, $tambahan, $aksi, $catatan): Incident {
            $dari = $insiden->status;
            $insiden->forceFill(array_merge(['status' => $tujuan], $tambahan))->save();
            IncidentStatusHistory::unguarded(fn (): mixed => $insiden->histories()->create(['from_status' => $dari, 'to_status' => $tujuan, 'actor_id' => $actor->id, 'note' => $catatan]));
            $this->audit->record($actor, $aksi, Incident::class, $insiden->id, [
                'event_id' => $insiden->event_id, 'old' => ['status' => $dari], 'new' => ['status' => $tujuan],
            ]);

            return $insiden->refresh();
        });
    }
}

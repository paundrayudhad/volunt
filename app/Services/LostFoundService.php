<?php

namespace App\Services;

use App\Models\Event;
use App\Models\LostFoundItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LostFoundService
{
    public function __construct(private AuditLogService $audit) {}

    /** @param array{kind: string, item_name: string, description?: ?string, location?: ?string, occurred_at?: ?string, photo?: ?UploadedFile} $data */
    public function report(Event $event, User $pelapor, array $data): LostFoundItem
    {
        abort_unless(in_array($data['kind'], LostFoundItem::KINDS, true), 422, 'Jenis laporan tidak dikenal.');
        $status = $data['kind'] === 'lost' ? 'open' : 'found';
        $foto = isset($data['photo']) ? StoredPhoto::fromUpload($data['photo'], 'lost-found') : null;

        try {
            return DB::transaction(function () use ($event, $pelapor, $data, $status, $foto): LostFoundItem {
                $item = LostFoundItem::unguarded(fn (): LostFoundItem => LostFoundItem::create([
                    'event_id' => $event->id,
                    'kind' => $data['kind'],
                    'item_name' => $data['item_name'],
                    'description' => $data['description'] ?? null,
                    'location' => $data['location'] ?? null,
                    'occurred_at' => $data['occurred_at'] ?? null,
                    'reporter_id' => $pelapor->id,
                    'status' => $status,
                    'photo_path' => $foto,
                ]));
                $this->audit->record($pelapor, 'lostfound.reported', LostFoundItem::class, $item->id, [
                    'event_id' => $event->id, 'new' => ['kind' => $item->kind, 'status' => $item->status],
                ]);

                return $item;
            });
        } catch (\Throwable $e) {
            StoredPhoto::delete($foto);

            throw $e;
        }
    }

    public function claim(LostFoundItem $item, User $pengklaim): LostFoundItem
    {
        abort_unless($item->status === 'found', 404, 'Barang tidak tersedia untuk diklaim.');
        abort_unless($item->claimant_id === null, 422, 'Barang sudah diklaim.');
        abort_unless((int) $item->reporter_id !== (int) $pengklaim->id, 422, 'Tidak dapat mengklaim laporan sendiri.');

        return DB::transaction(function () use ($item, $pengklaim): LostFoundItem {
            $terkunci = LostFoundItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless($terkunci->status === 'found', 404, 'Barang tidak tersedia untuk diklaim.');
            abort_unless($terkunci->claimant_id === null, 422, 'Barang sudah diklaim.');
            abort_unless((int) $terkunci->reporter_id !== (int) $pengklaim->id, 422, 'Tidak dapat mengklaim laporan sendiri.');
            $terkunci->forceFill(['status' => 'claimed', 'claimant_id' => $pengklaim->id, 'claimed_at' => now()])->save();
            $this->audit->record($pengklaim, 'lostfound.claimed', LostFoundItem::class, $terkunci->id, [
                'event_id' => $terkunci->event_id, 'old' => ['status' => 'found'], 'new' => ['status' => 'claimed'],
            ]);

            return $terkunci->refresh();
        });
    }

    public function resolveClaim(LostFoundItem $item, User $handler, string $keputusan, ?string $catatan = null): LostFoundItem
    {
        abort_unless($item->status === 'claimed', 422, 'Hanya klaim yang menunggu yang dapat diputuskan.');
        abort_unless(in_array($keputusan, ['returned', 'rejected'], true), 422, 'Keputusan klaim tidak dikenal.');

        return DB::transaction(function () use ($item, $handler, $keputusan, $catatan): LostFoundItem {
            if ($keputusan === 'returned') {
                $item->forceFill(['status' => 'returned', 'handler_id' => $handler->id])->save();
            } else {
                $item->forceFill(['status' => 'found', 'claimant_id' => null, 'claimed_at' => null, 'handler_id' => $handler->id])->save();
            }
            $this->audit->record($handler, 'lostfound.claim_resolved', LostFoundItem::class, $item->id, [
                'event_id' => $item->event_id, 'old' => ['status' => 'claimed'], 'new' => ['status' => $item->status, 'note' => $catatan],
            ]);

            return $item->refresh();
        });
    }

    public function close(LostFoundItem $item, User $actor): LostFoundItem
    {
        abort_unless(in_array($item->status, ['found', 'returned'], true), 422, 'Hanya item found/returned yang dapat ditutup.');

        return DB::transaction(function () use ($item, $actor): LostFoundItem {
            $dari = $item->status;
            $item->forceFill(['status' => 'closed'])->save();
            $this->audit->record($actor, 'lostfound.closed', LostFoundItem::class, $item->id, [
                'event_id' => $item->event_id, 'old' => ['status' => $dari], 'new' => ['status' => 'closed'],
            ]);

            return $item->refresh();
        });
    }
}

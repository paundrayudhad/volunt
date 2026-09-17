<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventService
{
    public const TRANSITIONS = [
        'draft' => ['published', 'cancelled'],
        'published' => ['registration_open', 'cancelled'],
        'registration_open' => ['registration_closed', 'cancelled'],
        'registration_closed' => ['ongoing', 'cancelled'],
        'ongoing' => ['completed', 'cancelled'],
        'completed' => ['archived'],
        'archived' => [],
        'cancelled' => [],
    ];

    public function __construct(private AuditLogService $audit) {}

    /** @param array<string, mixed> $data */
    public function createEvent(Organization $org, array $data, User $actor): Event
    {
        abort_unless($org->status === 'active', 422, 'Organisasi tidak aktif.');

        return DB::transaction(function () use ($org, $data, $actor): Event {
            $payload = array_intersect_key($data, array_flip((new Event)->getFillable()));
            $event = Event::unguarded(fn (): Event => Event::create([
                ...$payload,
                'organization_id' => $org->id,
                'status' => 'draft',
            ]));
            $this->audit->record($actor, 'event.created', Event::class, $event->id, [
                'organization_id' => $org->id,
                'event_id' => $event->id,
            ]);

            return $event;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateEvent(Event $event, array $data, User $actor): Event
    {
        $this->tolakTerminal($event);
        $payload = array_intersect_key($data, array_flip($event->getFillable()));
        abort_if($payload === [], 422, 'Tidak ada data event yang dapat diperbarui.');

        return DB::transaction(function () use ($event, $payload, $actor): Event {
            $old = $event->only(array_keys($payload));
            $event->fill($payload)->save();
            $this->audit->record($actor, 'event.updated', Event::class, $event->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'old' => $old,
                'new' => $payload,
            ]);

            return $event->refresh();
        });
    }

    public function deleteEvent(Event $event, User $actor): void
    {
        $this->tolakTerminal($event);

        DB::transaction(function () use ($event, $actor): void {
            $event->delete();
            $this->audit->record($actor, 'event.deleted', Event::class, $event->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);
        });
    }

    public function transitionTo(Event $event, string $next, User $actor, ?string $reason = null): Event
    {
        abort_unless(in_array($next, self::TRANSITIONS[$event->status] ?? [], true), 422, 'Transisi status tidak valid.');
        if ($next === 'cancelled') {
            abort_unless(trim((string) $reason) !== '', 422, 'Alasan pembatalan wajib.');
        }

        return DB::transaction(function () use ($event, $next, $actor, $reason): Event {
            $old = $event->status;
            $event->forceFill([
                'status' => $next,
                'published_at' => $next === 'published' ? now() : $event->published_at,
            ])->save();
            $this->audit->record($actor, "event.{$next}", Event::class, $event->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'old' => ['status' => $old],
                'new' => ['status' => $next, 'reason' => $reason],
            ]);

            return $event->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function createDivision(Event $event, array $data, User $actor): EventDivision
    {
        $this->tolakTerminal($event);

        return DB::transaction(function () use ($event, $data, $actor): EventDivision {
            $payload = array_intersect_key($data, array_flip((new EventDivision)->getFillable()));
            $division = EventDivision::unguarded(fn (): EventDivision => EventDivision::create([
                ...$payload,
                'event_id' => $event->id,
                'supervisor_id' => $data['supervisor_id'] ?? null,
            ]));
            $this->audit->record($actor, 'division.created', EventDivision::class, $division->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);

            return $division;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDivision(EventDivision $division, array $data, User $actor): EventDivision
    {
        $event = $division->event()->firstOrFail();
        $this->tolakTerminal($event);
        $payload = array_intersect_key($data, array_flip($division->getFillable()));
        $adaSupervisor = array_key_exists('supervisor_id', $data);
        abort_if($payload === [] && ! $adaSupervisor, 422, 'Tidak ada data divisi yang dapat diperbarui.');

        return DB::transaction(function () use ($division, $event, $payload, $data, $adaSupervisor, $actor): EventDivision {
            $old = $division->only(array_keys($payload));
            $division->fill($payload);
            if ($adaSupervisor) {
                $division->forceFill(['supervisor_id' => $data['supervisor_id']]);
            }
            $division->save();
            $this->audit->record($actor, 'division.updated', EventDivision::class, $division->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'old' => $old,
                'new' => $payload,
            ]);

            return $division->refresh();
        });
    }

    public function deleteDivision(EventDivision $division, User $actor): void
    {
        $event = $division->event()->firstOrFail();
        $this->tolakTerminal($event);

        DB::transaction(function () use ($division, $event, $actor): void {
            $division->delete();
            $this->audit->record($actor, 'division.deleted', EventDivision::class, $division->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function createRole(Event $event, EventDivision $division, array $data, User $actor): EventRole
    {
        $this->tolakTerminal($event);
        abort_unless($division->event_id === $event->id, 422, 'Divisi tidak termasuk event ini.');

        return DB::transaction(function () use ($event, $division, $data, $actor): EventRole {
            $payload = array_intersect_key($data, array_flip((new EventRole)->getFillable()));
            $role = EventRole::unguarded(fn (): EventRole => EventRole::create([
                ...$payload,
                'event_id' => $event->id,
                'division_id' => $division->id,
            ]));
            $this->audit->record($actor, 'role.created', EventRole::class, $role->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);

            return $role;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateRole(EventRole $role, array $data, User $actor): EventRole
    {
        $event = $role->event()->firstOrFail();
        $this->tolakTerminal($event);
        $payload = array_intersect_key($data, array_flip($role->getFillable()));
        abort_if($payload === [], 422, 'Tidak ada data role yang dapat diperbarui.');

        return DB::transaction(function () use ($role, $event, $payload, $actor): EventRole {
            $old = $role->only(array_keys($payload));
            $role->fill($payload)->save();
            $this->audit->record($actor, 'role.updated', EventRole::class, $role->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'old' => $old,
                'new' => $payload,
            ]);

            return $role->refresh();
        });
    }

    public function deleteRole(EventRole $role, User $actor): void
    {
        $event = $role->event()->firstOrFail();
        $this->tolakTerminal($event);

        DB::transaction(function () use ($role, $event, $actor): void {
            $role->delete();
            $this->audit->record($actor, 'role.deleted', EventRole::class, $role->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function createShift(Event $event, EventDivision $division, ?EventRole $role, array $data, User $actor): EventShift
    {
        $this->tolakTerminal($event);
        abort_unless($division->event_id === $event->id, 422, 'Divisi tidak termasuk event ini.');
        if ($role !== null) {
            abort_unless($role->event_id === $event->id, 422, 'Role tidak termasuk event ini.');
        }

        return DB::transaction(function () use ($event, $division, $role, $data, $actor): EventShift {
            $payload = array_intersect_key($data, array_flip((new EventShift)->getFillable()));
            $shift = EventShift::unguarded(fn (): EventShift => EventShift::create([
                ...$payload,
                'event_id' => $event->id,
                'division_id' => $division->id,
                'role_id' => $role?->id,
                'supervisor_id' => $data['supervisor_id'] ?? null,
            ]));
            $this->audit->record($actor, 'shift.created', EventShift::class, $shift->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);

            return $shift;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateShift(EventShift $shift, array $data, User $actor): EventShift
    {
        $event = $shift->event()->firstOrFail();
        $this->tolakTerminal($event);
        $payload = array_intersect_key($data, array_flip($shift->getFillable()));
        $adaRole = array_key_exists('role_id', $data);
        $adaSupervisor = array_key_exists('supervisor_id', $data);
        abort_if($payload === [] && ! $adaRole && ! $adaSupervisor, 422, 'Tidak ada data shift yang dapat diperbarui.');

        if ($adaRole && $data['role_id'] !== null) {
            $cek = EventRole::where('id', $data['role_id'])->where('event_id', $event->id)->exists();
            abort_unless($cek, 422, 'Role tidak termasuk event ini.');
        }

        return DB::transaction(function () use ($shift, $event, $payload, $data, $adaRole, $adaSupervisor, $actor): EventShift {
            $old = $shift->only(array_keys($payload));
            $shift->fill($payload);
            if ($adaRole) {
                $shift->forceFill(['role_id' => $data['role_id']]);
            }
            if ($adaSupervisor) {
                $shift->forceFill(['supervisor_id' => $data['supervisor_id']]);
            }
            $shift->save();
            $this->audit->record($actor, 'shift.updated', EventShift::class, $shift->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'old' => $old,
                'new' => $payload,
            ]);

            return $shift->refresh();
        });
    }

    public function deleteShift(EventShift $shift, User $actor): void
    {
        $event = $shift->event()->firstOrFail();
        $this->tolakTerminal($event);

        DB::transaction(function () use ($shift, $event, $actor): void {
            $shift->delete();
            $this->audit->record($actor, 'shift.deleted', EventShift::class, $shift->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);
        });
    }

    private function tolakTerminal(Event $event): void
    {
        abort_if($event->isTerminal(), 422, 'Operasi ditolak: event sudah diarsipkan atau dibatalkan.');
    }
}

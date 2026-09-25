<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\EventRole;
use App\Models\Registration;
use App\Models\RegistrationStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(
        private AuditLogService $audit,
        private QuotaService $quota
    ) {}

    public function invite(Event $event, User $actor, User $volunteer, ?int $roleId = null, ?string $message = null): EventInvitation
    {
        abort_if(in_array($event->status, ['archived', 'cancelled'], true), 422, 'Event sudah tidak aktif.');

        $isRegistered = Registration::where('event_id', $event->id)
            ->where('user_id', $volunteer->id)
            ->whereIn('status', ['pending', 'under_review', 'accepted', 'waitlisted'])
            ->exists();

        abort_if($isRegistered, 422, 'Relawan sudah terdaftar pada event ini.');

        $pendingExist = EventInvitation::where('event_id', $event->id)
            ->where('user_id', $volunteer->id)
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->exists();

        abort_if($pendingExist, 422, 'Undangan pending untuk relawan ini sudah ada.');

        return DB::transaction(function () use ($event, $actor, $volunteer, $roleId, $message): EventInvitation {
            $invitation = EventInvitation::unguarded(fn () => EventInvitation::create([
                'event_id' => $event->id,
                'user_id' => $volunteer->id,
                'role_id' => $roleId,
                'invited_by_id' => $actor->id,
                'message' => $message,
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
            ]));

            $this->audit->record($actor, 'talent.invited', Event::class, $event->id, [
                'invitation_id' => $invitation->id,
                'volunteer_id' => $volunteer->id,
            ]);

            return $invitation;
        });
    }

    public function respond(EventInvitation $invitation, User $volunteer, string $action): EventInvitation
    {
        abort_unless($invitation->user_id === $volunteer->id, 403, 'Otorisasi tidak sah.');
        abort_unless($invitation->status === 'pending', 422, 'Undangan sudah direspon atau dibatalkan.');
        abort_if($invitation->isExpired(), 422, 'Undangan sudah kadaluarsa.');
        abort_unless(in_array($action, ['accepted', 'declined'], true), 422, 'Aksi undangan tidak sah.');

        return DB::transaction(function () use ($invitation, $volunteer, $action): EventInvitation {
            if ($action === 'accepted') {
                $roleId = $invitation->role_id;
                if ($roleId === null) {
                    $firstRole = EventRole::where('event_id', $invitation->event_id)->first();
                    $roleId = $firstRole?->id;
                }

                if ($roleId !== null) {
                    $role = EventRole::findOrFail($roleId);
                    $this->quota->accept($role);
                }

                $reg = Registration::unguarded(fn () => Registration::create([
                    'event_id' => $invitation->event_id,
                    'user_id' => $volunteer->id,
                    'role_id' => $roleId,
                    'status' => 'accepted',
                    'submitted_at' => now(),
                    'reviewed_by' => $invitation->invited_by_id,
                    'reviewed_at' => now(),
                    'idempotency_key' => Str::uuid()->toString(),
                ]));

                RegistrationStatusHistory::unguarded(fn () => $reg->histories()->create([
                    'from_status' => null,
                    'to_status' => 'accepted',
                    'changed_by' => $invitation->invited_by_id,
                    'reason' => 'Undangan event diterima oleh relawan',
                    'created_at' => now(),
                ]));
            }

            $invitation->forceFill([
                'status' => $action,
                'responded_at' => now(),
            ])->save();

            $this->audit->record($volunteer, 'talent_invitation.'.$action, EventInvitation::class, $invitation->id, []);

            return $invitation;
        });
    }

    public function cancel(EventInvitation $invitation, User $actor): EventInvitation
    {
        abort_unless($invitation->status === 'pending', 422, 'Hanya undangan pending yang dapat dibatalkan.');

        return DB::transaction(function () use ($invitation, $actor): EventInvitation {
            $invitation->forceFill(['status' => 'cancelled'])->save();

            $this->audit->record($actor, 'talent_invitation.cancelled', EventInvitation::class, $invitation->id, []);

            return $invitation;
        });
    }
}

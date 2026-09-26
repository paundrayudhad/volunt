<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventRole;
use App\Models\EventShift;
use App\Models\Incident;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

class EventAnalyticsService
{
    /**
     * @return array{
     *     registration_funnel: array{total: int, pending: int, under_review: int, accepted: int, rejected: int, waitlisted: int, cancelled: int, withdrawn: int},
     *     attendance_summary: array{total_assignments: int, present: int, late: int, absent: int, attendance_rate_pct: float},
     *     role_utilization: array<int, array{role_name: string, quota: int, accepted_count: int, utilization_pct: float}>,
     *     shift_utilization: array<int, array{shift_name: string, capacity: int, filled_count: int, utilization_pct: float}>,
     *     incident_summary: array{total: int, open: int, assigned: int, in_progress: int, resolved: int, closed: int, critical_count: int},
     *     certificate_summary: array{issued: int, revoked: int}
     * }
     */
    public function getEventSummary(Event $event): array
    {
        // 1. Single aggregate query untuk Registration Funnel
        $regCounts = DB::table('registrations')
            ->where('event_id', $event->id)
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE status = 'under_review') AS under_review,
                COUNT(*) FILTER (WHERE status = 'accepted') AS accepted,
                COUNT(*) FILTER (WHERE status = 'rejected') AS rejected,
                COUNT(*) FILTER (WHERE status = 'waitlisted') AS waitlisted,
                COUNT(*) FILTER (WHERE status = 'cancelled') AS cancelled,
                COUNT(*) FILTER (WHERE status = 'withdrawn') AS withdrawn
            ")
            ->first();

        // 2. Attendance Summary
        $totalAssignments = DB::table('assignments')
            ->where('event_id', $event->id)
            ->whereIn('status', ['assigned', 'reassigned', 'confirmed', 'completed'])
            ->whereNull('deleted_at')
            ->count();

        $attCounts = DB::table('attendances')
            ->where('event_id', $event->id)
            ->selectRaw("
                COUNT(*) FILTER (WHERE status = 'present') AS present,
                COUNT(*) FILTER (WHERE status = 'late') AS late,
                COUNT(*) FILTER (WHERE status = 'absent') AS absent
            ")
            ->first();

        $presentCount = (int) ($attCounts->present ?? 0);
        $lateCount = (int) ($attCounts->late ?? 0);
        $absentCount = (int) ($attCounts->absent ?? 0);
        $hadir = $presentCount + $lateCount;
        $attendanceRate = $totalAssignments > 0 ? round(($hadir / $totalAssignments) * 100, 2) : 0.0;

        // 3. Role Utilization
        $roles = EventRole::where('event_id', $event->id)->get();
        $roleUtilization = $roles->map(function (EventRole $r) {
            $util = $r->quota > 0 ? round(($r->accepted_count / $r->quota) * 100, 2) : 0.0;

            return [
                'role_name' => $r->name,
                'quota' => $r->quota,
                'accepted_count' => $r->accepted_count,
                'utilization_pct' => $util,
            ];
        })->values()->all();

        // 4. Shift Utilization
        $shifts = EventShift::where('event_id', $event->id)->get();
        $shiftUtilization = $shifts->map(function (EventShift $s) {
            $capacity = $s->capacity ?? 0;
            $filledCount = (int) $s->filled_count;
            $util = $capacity > 0 ? round(($filledCount / $capacity) * 100, 2) : 0.0;
            $shiftName = $s->start_at ? $s->start_at->format('d M Y H:i') : "Shift #{$s->id}";

            return [
                'shift_name' => $shiftName,
                'capacity' => $capacity,
                'filled_count' => $filledCount,
                'utilization_pct' => $util,
            ];
        })->values()->all();

        // 5. Incident Summary
        $incCounts = DB::table('incidents')
            ->where('event_id', $event->id)
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'open') AS open,
                COUNT(*) FILTER (WHERE status = 'assigned') AS assigned,
                COUNT(*) FILTER (WHERE status = 'in_progress') AS in_progress,
                COUNT(*) FILTER (WHERE status = 'resolved') AS resolved,
                COUNT(*) FILTER (WHERE status = 'closed') AS closed,
                COUNT(*) FILTER (WHERE priority = 'critical') AS critical_count
            ")
            ->first();

        // 6. Certificate Summary
        $certCounts = DB::table('certificates')
            ->where('event_id', $event->id)
            ->selectRaw('
                COUNT(*) FILTER (WHERE revoked_at IS NULL) AS issued,
                COUNT(*) FILTER (WHERE revoked_at IS NOT NULL) AS revoked
            ')
            ->first();

        return [
            'registration_funnel' => [
                'total' => (int) ($regCounts->total ?? 0),
                'pending' => (int) ($regCounts->pending ?? 0),
                'under_review' => (int) ($regCounts->under_review ?? 0),
                'accepted' => (int) ($regCounts->accepted ?? 0),
                'rejected' => (int) ($regCounts->rejected ?? 0),
                'waitlisted' => (int) ($regCounts->waitlisted ?? 0),
                'cancelled' => (int) ($regCounts->cancelled ?? 0),
                'withdrawn' => (int) ($regCounts->withdrawn ?? 0),
            ],
            'attendance_summary' => [
                'total_assignments' => $totalAssignments,
                'present' => $presentCount,
                'late' => $lateCount,
                'absent' => $absentCount,
                'attendance_rate_pct' => (float) $attendanceRate,
            ],
            'role_utilization' => $roleUtilization,
            'shift_utilization' => $shiftUtilization,
            'incident_summary' => [
                'total' => (int) ($incCounts->total ?? 0),
                'open' => (int) ($incCounts->open ?? 0),
                'assigned' => (int) ($incCounts->assigned ?? 0),
                'in_progress' => (int) ($incCounts->in_progress ?? 0),
                'resolved' => (int) ($incCounts->resolved ?? 0),
                'closed' => (int) ($incCounts->closed ?? 0),
                'critical_count' => (int) ($incCounts->critical_count ?? 0),
            ],
            'certificate_summary' => [
                'issued' => (int) ($certCounts->issued ?? 0),
                'revoked' => (int) ($certCounts->revoked ?? 0),
            ],
        ];
    }
}

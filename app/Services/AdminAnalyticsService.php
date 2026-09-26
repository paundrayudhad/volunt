<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    /**
     * @return array{
     *     organizations: array{total: int, active: int, pending: int, suspended: int},
     *     events: array{total: int, published: int, completed: int},
     *     volunteers: array{total_users: int, completed_profiles: int},
     *     certificates: array{total_issued: int, total_verifications: int}
     * }
     */
    public function getPlatformSummary(): array
    {
        $orgCounts = DB::table('organizations')
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'active') AS active,
                COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE status = 'suspended') AS suspended
            ")
            ->first();

        $eventCounts = DB::table('events')
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'published') AS published,
                COUNT(*) FILTER (WHERE status = 'completed') AS completed
            ")
            ->first();

        $totalUsers = DB::table('users')->count();
        $completedProfiles = DB::table('volunteer_profiles')->count();

        $certCount = DB::table('certificates')->whereNull('revoked_at')->count();
        $verifCount = DB::table('certificate_verifications')->count();

        return [
            'organizations' => [
                'total' => (int) ($orgCounts->total ?? 0),
                'active' => (int) ($orgCounts->active ?? 0),
                'pending' => (int) ($orgCounts->pending ?? 0),
                'suspended' => (int) ($orgCounts->suspended ?? 0),
            ],
            'events' => [
                'total' => (int) ($eventCounts->total ?? 0),
                'published' => (int) ($eventCounts->published ?? 0),
                'completed' => (int) ($eventCounts->completed ?? 0),
            ],
            'volunteers' => [
                'total_users' => $totalUsers,
                'completed_profiles' => $completedProfiles,
            ],
            'certificates' => [
                'total_issued' => $certCount,
                'total_verifications' => $verifCount,
            ],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class AuditLogService
{
    public function record(User $actor, string $action, string $resourceType, int|string $resourceId, array $context = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actor->id,
            'organization_id' => $context['organization_id'] ?? null,
            'event_id' => $context['event_id'] ?? null,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => (string) $resourceId,
            'old_values' => $context['old'] ?? null,
            'new_values' => $context['new'] ?? null,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 512),
            'request_id' => request()->header('X-Request-ID', substr((string) Str::uuid(), 0, 36)),
        ]);
    }
}

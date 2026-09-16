<?php

namespace App\Services;

use App\Models\SecurityLog;
use App\Models\User;

class SecurityService
{
    public function record(?User $actor, string $type, array $context = []): SecurityLog
    {
        return SecurityLog::create([
            'actor_id' => $actor?->id,
            'type' => $type,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 512),
            'context' => $context,
        ]);
    }
}

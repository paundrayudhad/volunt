<?php

namespace App\Services;

use App\Exceptions\QuotaFullException;
use App\Models\EventRole;

class QuotaService
{
    public function accept(EventRole $role): EventRole
    {
        $terkunci = EventRole::whereKey($role->id)->lockForUpdate()->firstOrFail();
        if ($terkunci->accepted_count >= $terkunci->quota) {
            throw new QuotaFullException;
        }
        $terkunci->increment('accepted_count');

        return $terkunci->refresh();
    }

    public function release(EventRole $role): EventRole
    {
        $terkunci = EventRole::whereKey($role->id)->lockForUpdate()->firstOrFail();
        if ($terkunci->accepted_count > 0) {
            $terkunci->decrement('accepted_count');
        }

        return $terkunci->refresh();
    }
}

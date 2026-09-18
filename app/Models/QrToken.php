<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrToken extends Model
{
    protected $fillable = [
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}

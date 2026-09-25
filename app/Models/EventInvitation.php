<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventInvitation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'accepted', 'declined', 'expired', 'cancelled'];

    protected $fillable = [];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<EventRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(EventRole::class, 'role_id');
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}

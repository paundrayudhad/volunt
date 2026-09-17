<?php

namespace App\Models;

use Database\Factories\EventShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventShift extends Model
{
    /** @use HasFactory<EventShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'start_at',
        'end_at',
        'location',
        'capacity',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<EventDivision, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'division_id');
    }

    /** @return BelongsTo<EventRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(EventRole::class, 'role_id');
    }

    /** @return BelongsTo<User, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function isPast(): bool
    {
        return $this->end_at !== null && $this->end_at->isPast();
    }
}

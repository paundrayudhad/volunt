<?php

namespace App\Models;

use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory, SoftDeletes;

    public const TRANSITIONS = [
        'assigned' => ['confirmed', 'completed', 'cancelled'],
        'confirmed' => ['completed', 'cancelled'],
        'reassigned' => ['confirmed', 'completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'registration_id',
        'division_id',
        'role_id',
        'shift_id',
        'location',
        'supervisor_id',
    ];

    /** @return BelongsTo<Registration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

    /** @return BelongsTo<EventShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(EventShift::class, 'shift_id');
    }

    /** @return BelongsTo<User, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** @return HasMany<AssignmentHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(AssignmentHistory::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<QrToken, $this> */
    public function qrTokens(): HasMany
    {
        return $this->hasMany(QrToken::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['assigned', 'reassigned', 'confirmed'], true);
    }
}

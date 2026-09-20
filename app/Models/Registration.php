<?php

namespace App\Models;

use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    public const TRANSITIONS = [
        'pending' => ['under_review', 'cancelled', 'withdrawn'],
        'under_review' => ['accepted', 'rejected', 'waitlisted', 'cancelled', 'withdrawn'],
        'waitlisted' => ['accepted', 'rejected', 'cancelled', 'withdrawn'],
        'accepted' => ['cancelled'],
        'rejected' => [],
        'cancelled' => [],
        'withdrawn' => [],
    ];

    protected $fillable = [
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

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

    /** @return BelongsTo<EventRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(EventRole::class, 'role_id');
    }

    /** @return HasMany<RegistrationAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationAnswer::class);
    }

    /** @return HasOne<Assignment, $this> */
    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class);
    }

    /** @return HasOne<Certificate, $this> */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    /** @return HasMany<RegistrationStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(RegistrationStatusHistory::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['rejected', 'cancelled', 'withdrawn'], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'under_review', 'accepted', 'waitlisted'], true);
    }
}

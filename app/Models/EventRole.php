<?php

namespace App\Models;

use Database\Factories\EventRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRole extends Model
{
    /** @use HasFactory<EventRoleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'quota',
        'requirements',
        'location',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'requirements' => 'array',
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

    /** @return HasMany<EventShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(EventShift::class, 'role_id');
    }

    /** @return HasMany<Registration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'role_id');
    }

    public function remainingQuota(): int
    {
        return max(0, (int) $this->quota - (int) $this->accepted_count);
    }
}

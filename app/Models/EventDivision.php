<?php

namespace App\Models;

use Database\Factories\EventDivisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventDivision extends Model
{
    /** @use HasFactory<EventDivisionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<EventRole, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(EventRole::class, 'division_id');
    }

    /** @return HasMany<EventShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(EventShift::class, 'division_id');
    }

    /** @return BelongsTo<User, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}

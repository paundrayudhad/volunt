<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artist extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['scheduled', 'soundcheck', 'performing', 'done', 'cancelled'];

    public const ATTENDANCES = ['expected', 'arrived', 'no_show'];

    /** @var array<string, array<int, string>> */
    public const NEXT = [
        'scheduled' => ['soundcheck'],
        'soundcheck' => ['performing'],
        'performing' => ['done'],
        'done' => [],
        'cancelled' => [],
    ];

    public const DEFAULT_DURATION = 60;

    protected $fillable = [];

    /** @var array<string, string> */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'rider_fulfilled' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<ArtistLiaison, $this> */
    public function liaisons(): HasMany
    {
        return $this->hasMany(ArtistLiaison::class)->whereNull('artist_liaisons.deleted_at');
    }

    /** @return HasMany<ArtistStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(ArtistStatusHistory::class);
    }

    /** @return HasMany<ArtistNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ArtistNote::class);
    }

    public function endsAt(): ?CarbonInterface
    {
        if ($this->scheduled_at === null) {
            return null;
        }

        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes ?? self::DEFAULT_DURATION);
    }
}

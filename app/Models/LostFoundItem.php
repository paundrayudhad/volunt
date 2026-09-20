<?php

namespace App\Models;

use Database\Factories\LostFoundItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LostFoundItem extends Model
{
    /** @use HasFactory<LostFoundItemFactory> */
    use HasFactory, SoftDeletes;

    public const KINDS = ['lost', 'found'];

    public const STATUSES = ['open', 'found', 'claimed', 'returned', 'closed'];

    protected $fillable = [];

    /** @var array<string, string> */
    protected $casts = [
        'occurred_at' => 'datetime',
        'claimed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    /** @return BelongsTo<User, $this> */
    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_id');
    }

    /** @return HasMany<Incident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'lost_found_item_id');
    }
}

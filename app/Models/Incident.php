<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory, SoftDeletes;

    public const CATEGORIES = ['medical', 'security', 'crowd', 'technical', 'lost_found', 'other'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['open', 'assigned', 'in_progress', 'resolved', 'closed'];

    /** @var array<string, array<int, string>> */
    public const NEXT = [
        'open' => ['assigned'],
        'assigned' => ['in_progress'],
        'in_progress' => ['resolved'],
        'resolved' => ['closed'],
        'closed' => [],
    ];

    protected $fillable = [];

    /** @var array<string, string> */
    protected $casts = [
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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<LostFoundItem, $this> */
    public function lostFoundItem(): BelongsTo
    {
        return $this->belongsTo(LostFoundItem::class);
    }

    /** @return HasMany<IncidentStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(IncidentStatusHistory::class);
    }

    public function isCritical(): bool
    {
        return $this->priority === 'critical';
    }
}

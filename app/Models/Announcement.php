<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'target_type',
        'target_id',
        'title',
        'body',
        'published_at',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null
            && ! $this->published_at->isFuture()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}

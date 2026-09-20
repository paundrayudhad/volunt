<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'venue',
        'address',
        'latitude',
        'longitude',
        'timezone',
        'start_at',
        'end_at',
        'registration_start_at',
        'registration_end_at',
        'capacity',
        'contact',
        'branding',
        'banner_path',
        'thumbnail_path',
        'terms',
        'privacy_notice',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'contact' => 'array',
        'branding' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'registration_start_at' => 'datetime',
        'registration_end_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<EventDivision, $this> */
    public function divisions(): HasMany
    {
        return $this->hasMany(EventDivision::class);
    }

    /** @return HasMany<EventRole, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(EventRole::class);
    }

    /** @return HasMany<EventShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(EventShift::class);
    }

    /** @return HasMany<Registration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<Announcement, $this> */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    /** @return HasMany<Certificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /** @return HasMany<EventCustomField, $this> */
    public function customFields(): HasMany
    {
        return $this->hasMany(EventCustomField::class);
    }

    /** @param  Builder<Event>  $query */
    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('organization_id', $orgId);
    }

    /** @param  Builder<Event>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled', 'archived']);
    }

    /** @param  Builder<Event>  $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'registration_open', 'registration_closed', 'ongoing']);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['archived', 'cancelled'], true);
    }

    public function isPubliclyVisible(): bool
    {
        return in_array($this->status, ['published', 'registration_open', 'registration_closed', 'ongoing'], true);
    }
}

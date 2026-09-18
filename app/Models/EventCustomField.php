<?php

namespace App\Models;

use Database\Factories\EventCustomFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCustomField extends Model
{
    /** @use HasFactory<EventCustomFieldFactory> */
    use HasFactory;

    public const TYPES = [
        'text',
        'textarea',
        'email',
        'phone',
        'number',
        'date',
        'time',
        'select',
        'multi_select',
        'radio',
        'checkbox',
        'url',
        'file',
    ];

    protected $fillable = [
        'label',
        'type',
        'required',
        'placeholder',
        'validation_rule',
        'sort_order',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<EventCustomFieldOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(EventCustomFieldOption::class, 'event_custom_field_id');
    }
}

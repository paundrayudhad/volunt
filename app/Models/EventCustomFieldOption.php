<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCustomFieldOption extends Model
{
    protected $fillable = [
        'label',
        'value',
        'sort_order',
    ];

    /** @return BelongsTo<EventCustomField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(EventCustomField::class, 'event_custom_field_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationAnswer extends Model
{
    protected $fillable = [
        'value_text',
        'value_jsonb',
        'file_path',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value_jsonb' => 'array',
    ];

    /** @return BelongsTo<Registration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /** @return BelongsTo<EventCustomField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(EventCustomField::class, 'event_custom_field_id');
    }
}

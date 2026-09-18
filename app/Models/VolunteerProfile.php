<?php

namespace App\Models;

use Database\Factories\VolunteerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VolunteerProfile extends Model
{
    /** @use HasFactory<VolunteerProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'full_name',
        'phone',
        'city',
        'education',
        'experience',
        'skills',
        'portfolio_url',
        'social_links',
        'availability',
        'visibility',
        'date_of_birth',
        'emergency_contact',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'skills' => 'array',
        'social_links' => 'array',
        'availability' => 'array',
        'date_of_birth' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

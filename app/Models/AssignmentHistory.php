<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletionRequest extends Model
{
    protected $table = 'deletion_requests';

    protected $fillable = [
        'child_id',
        'requested_by',
        'requested_at',
        'scheduled_for',
        'status',
        'reason',
        'completed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

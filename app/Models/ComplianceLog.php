<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceLog extends Model
{
    protected $table = 'compliance_logs';

    protected $fillable = [
        'child_id',
        'user_id',
        'action',
        'data_type',
        'reason',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'timestamp',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'timestamp' => 'datetime',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

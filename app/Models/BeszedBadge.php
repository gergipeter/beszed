<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeszedBadge extends Model
{
    protected $table = 'beszed_badges';

    public $timestamps = false;

    protected $fillable = [
        'child_id',
        'badge',
        'name',
        'description',
        'icon',
        'earned_at',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}

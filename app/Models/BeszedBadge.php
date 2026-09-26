<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A sticker a child has earned (keys: config/beszed.php → rewards.badges). */
class BeszedBadge extends Model
{
    public const CREATED_AT = 'earned_at';

    public const UPDATED_AT = null;

    protected $fillable = ['child_id', 'badge'];

    protected $casts = ['earned_at' => 'datetime'];
}

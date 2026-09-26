<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Csillám's suggested games for one day (`games`) and which of them were played (`done`). */
class BeszedDailyPath extends Model
{
    protected $fillable = ['child_id', 'day', 'games', 'done', 'completed_at'];

    // `day` stays a plain "Y-m-d" string: it's a calendar day, not a moment.
    protected $casts = ['games' => 'array', 'done' => 'array', 'completed_at' => 'datetime'];
}

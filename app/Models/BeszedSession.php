<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A finished game (all rounds played). */
class BeszedSession extends Model
{
    public const CREATED_AT = 'completed_at';

    public const UPDATED_AT = null;

    protected $fillable = ['child_id', 'game', 'level', 'rounds', 'correct', 'first_try', 'duration_ms'];

    protected $casts = ['completed_at' => 'datetime'];
}

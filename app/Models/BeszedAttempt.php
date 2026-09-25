<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeszedAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['child_id', 'game', 'content_item_id', 'level', 'correct', 'tries', 'duration_ms'];

    protected $casts = ['correct' => 'boolean'];
}

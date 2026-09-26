<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-child module settings: what Csillám is wearing and her sticker scene. */
class BeszedProfile extends Model
{
    protected $fillable = ['child_id', 'accessories', 'scene'];

    protected $casts = [
        'accessories' => 'array',
        'scene' => 'array',
    ];
}

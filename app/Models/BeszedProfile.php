<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-child module settings: what Csillám is wearing. */
class BeszedProfile extends Model
{
    protected $fillable = ['child_id', 'accessory'];
}

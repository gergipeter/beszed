<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeszedSkillLevel extends Model
{
    protected $fillable = ['child_id', 'game', 'level', 'streak'];
}

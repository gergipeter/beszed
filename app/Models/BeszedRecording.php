<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeszedRecording extends Model
{
    protected $fillable = ['user_id', 'line_key', 'disk', 'path', 'mime', 'size'];
}

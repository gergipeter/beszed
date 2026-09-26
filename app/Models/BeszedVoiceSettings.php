<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-parent Csillám voice preferences. */
class BeszedVoiceSettings extends Model
{
    protected $fillable = ['user_id', 'voice', 'rate', 'pitch', 'prefer_server_tts', 'muted'];

    protected $casts = [
        'prefer_server_tts' => 'boolean',
        'muted' => 'boolean',
    ];
}

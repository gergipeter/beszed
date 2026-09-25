<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BeszedContentItem extends Model
{
    protected $fillable = ['game', 'level', 'payload', 'active'];

    protected $casts = ['payload' => 'array', 'active' => 'boolean'];

    public function scopeForGame(Builder $q, string $game): Builder
    {
        return $q->where('game', $game)->where('active', true);
    }
}

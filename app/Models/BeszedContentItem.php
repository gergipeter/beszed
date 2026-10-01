<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A word, sentence or picture set a game builds its rounds from. */
class BeszedContentItem extends Model
{
    protected $fillable = ['game', 'level', 'payload', 'active', 'source', 'seed_key', 'edited_at', 'status'];

    protected $casts = ['payload' => 'array', 'active' => 'boolean', 'edited_at' => 'datetime'];

    public function scopeForGame(Builder $q, string $game): Builder
    {
        return $q->where('game', $game)->where('active', true)->where('status', 'live');
    }

    /** The ARASAAC pictograms (ids) the item shows with no emoji to fall back on. @return list<int> */
    public function arasaacIds(): array
    {
        preg_match_all('/arasaac:(\d+)/', json_encode($this->payload), $m);

        return array_map('intval', array_unique($m[1]));
    }

    /** Stable id of a seed payload (same encoding as the array cast, so stored rows hash the same). */
    public static function seedKey(array $payload): string
    {
        return sha1(json_encode($payload));
    }
}

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

    /**
     * The ARASAAC pictograms (ids) the item shows: every string in its payload that is either
     * already written as "arasaac:<id>" (rare, content-editor-entered) or is an emoji the word bank
     * maps to one (the normal case — content stores the plain emoji, "arasaac:<id>" only ever appears
     * live, from Pictures::apply()). Used to decide, with pictograms off, whether every picture the
     * item would show has a substitute to fall back on.
     * @return list<int>
     */
    public function arasaacIds(): array
    {
        $byEmoji = \App\Beszed\Content\Pictures::map();
        $ids = [];
        $payload = $this->payload; // array_walk_recursive needs a real variable, not the cast accessor
        array_walk_recursive($payload, function ($value) use (&$ids, $byEmoji) {
            if (! is_string($value)) {
                return;
            }
            if (preg_match('/^arasaac:(\d+)/', $value, $m)) {
                $ids[] = (int) $m[1];
            } elseif (isset($byEmoji[$value])) {
                $ids[] = (int) explode(':', $byEmoji[$value])[1];
            }
        });

        return array_values(array_unique($ids));
    }

    /** Stable id of a seed payload (same encoding as the array cast, so stored rows hash the same). */
    public static function seedKey(array $payload): string
    {
        return sha1(json_encode($payload));
    }
}

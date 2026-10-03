<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * A board game with speaking tasks (like a speech therapist's "őszi lépegető" sheet): the child rolls a die,
 * steps along a path from Start to Cél, and every field asks something to say, copy, count or do.
 * The theme is a season: the one the child picked, else the one it is now. $level = fields on the path:
 * 1 → 8, 2 → 12, 3 → 16.
 */
class LepegetoRounds extends RoundFactory
{
    /** level → task fields between Start and Cél */
    public const FIELDS = [1 => 8, 2 => 12, 3 => 16];

    /** theme → [name, emoji of Start/Cél, the token the child moves] */
    public const THEMES = [
        'osz' => ['ősz', '🍁', '🐿️'],
        'tel' => ['tél', '❄️', '🐧'],
        'tavasz' => ['tavasz', '🌷', '🐞'],
        'nyar' => ['nyár', '☀️', '🦀'],
    ];

    public function build(Collection $items, int $level, int $count): array
    {
        $theme = $this->theme();
        $pool = $items->filter(fn ($i) => ($i->payload['theme'] ?? null) === $theme)->values();
        $pool = $pool->isEmpty() ? $items : $pool;
        [$name, $mark, $token] = self::THEMES[$theme];
        $fields = self::FIELDS[$level] ?? self::FIELDS[1];
        $rounds = [];

        for ($r = 0; $r < $count; $r++) {
            $tasks = $this->spread($this->weightedShuffle($pool)->take($fields)->values());
            $tiles = [['kind' => 'start', 'emoji' => $mark, 'text' => 'Start']];
            foreach ($tasks as $task) {
                $tiles[] = ['kind' => $task->payload['kind'], 'emoji' => $task->payload['emoji'], 'text' => $task->payload['text']];
            }
            $tiles[] = ['kind' => 'goal', 'emoji' => $mark, 'text' => 'Cél'];

            $prompt = $r === 0
                ? "Lépegetünk! Dobj a kockával, és csináld meg, amit a mezők kérnek. Legyen ez a mi $name lépegetőnk!"
                : 'Dobj a kockával!';
            $rounds[] = $this->round('board', $prompt, [
                'theme' => $name,
                'token' => $token,
                'tiles' => $tiles,
                'dieMax' => 3,
                'onCorrect' => 'Célba értél! Nagyon ügyes vagy!',
            ]);
        }

        return $rounds;
    }

    /** The picked theme, or the season of the year. */
    private function theme(): string
    {
        $picked = $this->options['category'] ?? null;
        if (isset(self::THEMES[$picked])) {
            return $picked;
        }

        return match (true) {
            now()->month >= 12 || now()->month <= 2 => 'tel',
            now()->month <= 5 => 'tavasz',
            now()->month <= 8 => 'nyar',
            default => 'osz',
        };
    }

    /** Same order, but no two fields of one kind side by side where it can be helped. */
    private function spread(Collection $tasks): array
    {
        $left = $tasks->all();
        $out = [];
        while ($left) {
            $last = $out ? end($out)->payload['kind'] : null;
            $i = array_key_first(array_filter($left, fn ($t) => $t->payload['kind'] !== $last)) ?? array_key_first($left);
            $out[] = $left[$i];
            unset($left[$i]);
        }

        return $out;
    }
}

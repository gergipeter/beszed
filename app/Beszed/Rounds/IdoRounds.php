<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Reading an analogue clock: "Hány óra van?" with three digital times to pick from, one of them with the same hour
 * or the same minutes, so the child has to look at both hands. Each content item is one time ("3:30").
 * $level = the content's level: whole hours → + half hours (fél négy) → + quarters (negyed négy, háromnegyed négy).
 */
class IdoRounds extends RoundFactory
{
    private const BASE = [1 => 'egy', 2 => 'kettő', 3 => 'három', 4 => 'négy', 5 => 'öt', 6 => 'hat', 7 => 'hét', 8 => 'nyolc', 9 => 'kilenc', 10 => 'tíz', 11 => 'tizenegy', 12 => 'tizenkettő'];

    /** "két óra": the number before a noun. */
    private const BEFORE_NOUN = [2 => 'két', 12 => 'tizenkét'];

    public function build(Collection $items, int $level, int $count): array
    {
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $level)->values();
        $pool = $pool->isEmpty() ? $items : $pool;
        // the newest kind of time comes most often
        $this->weights = $pool->mapWithKeys(fn ($i) => [$i->id => (($i->level ?? 1) === $level ? 3.0 : 1.0) * ($this->weights[$i->id] ?? 1.0)])->all();
        $rounds = [];

        foreach ($this->cycle($pool, $count)->values() as $r => $item) {
            [$h, $m] = $this->parts($item->payload['time']);
            $sameHour = $pool->filter(fn ($i) => $i->id !== $item->id && $this->parts($i->payload['time'])[0] === $h)->shuffle()->take(1);
            $rest = $pool->filter(fn ($i) => $i->id !== $item->id && ! $sameHour->contains('id', $i->id))->shuffle()->take(2 - $sameHour->count());
            $opts = collect([$item])->merge($sameHour)->merge($rest)->shuffle()->values();
            $spoken = $this->say($h, $m);

            $rounds[] = $this->round('choice', $r === 0
                ? 'Nézd meg jól az órát! A kis mutató az órát, a nagy mutató a perceket mutatja. Hány óra van?'
                : 'Hány óra van?', [
                'stimulus' => ['emoji' => '', 'clock' => ['hour' => $h, 'minute' => $m]],
                'layout' => 'three',
                'options' => $opts->map(fn ($o) => ['id' => (string) $o->id, 'letter' => $o->payload['time']])->all(),
                'answer' => (string) $item->id,
                'onCorrect' => 'Igen! '.$this->ucfirst($spoken).'.',
                'onWrong' => 'Nem ez az. Nézd meg a kis és a nagy mutatót is, aztán próbáld újra!',
            ], $item->id);
        }

        return $rounds;
    }

    /** @return array{0: int, 1: int} */
    private function parts(string $time): array
    {
        [$h, $m] = explode(':', $time);

        return [(int) $h, (int) $m];
    }

    /** 3:00 → "három óra", 3:15 → "negyed négy", 3:30 → "fél négy", 3:45 → "háromnegyed négy". */
    public function say(int $h, int $m): string
    {
        $next = self::BASE[$h % 12 + 1];

        return match ($m) {
            15 => "negyed $next",
            30 => "fél $next",
            45 => "háromnegyed $next",
            default => (self::BEFORE_NOUN[$h] ?? self::BASE[$h]).' óra',
        };
    }
}

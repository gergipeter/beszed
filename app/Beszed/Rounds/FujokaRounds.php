<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Fújóka: breath control, the blowing exercises of speech therapy. The microphone hears the blow (engine
 * `voice`, mode `blow`) and the scene answers: candles go out, seeds fly, the boat sails, the feather floats.
 * $level (1–100, tier()): tier 1 (1–33) → short separate puffs (one candle, bubble or tuft of seeds each) ·
 * tier 2 (34–66) → one long blow, held from 1.5 s on the session's first round to 2.5 s on its last · tier 3
 * (67–100) → a gentle, steady blow, in turn with soft-and-strong rounds. The puff count and the number of
 * alternating steps come from the content item itself, so they already vary within a tier without needing
 * the adaptive level; only which kind of blow is asked for is level-driven.
 * Without a microphone a parent's button counts each blow, so nothing is graded: every finish is tries 1.
 */
class FujokaRounds extends RoundFactory
{
    /** Which scenes can show which kind of blowing (ContentRules checks the content against it). */
    public const SCENES = [
        'puffs' => ['candles', 'bubbles', 'dandelion'],
        'long' => ['boat', 'pinwheel', 'dandelion'],
        'gentle' => ['feather', 'bubbles', 'pinwheel'],
        'alternate' => ['pinwheel', 'boat'],
    ];

    /** tier (1–3, see tier()) → the kinds of blowing played (tier 3 takes them in turn); content items are keyed the same 1–3 */
    public const KINDS = [1 => ['puffs'], 2 => ['long'], 3 => ['gentle', 'alternate']];

    /** Tier 2: the long blow, from the first round to the last. */
    public const HOLD_MS = [1500, 2500];

    /** Tier 3: how long the gentle blow lasts, and each soft or strong step. */
    public const SOFT_MS = 3000;

    public const STEP_MS = 900;

    private const TIMES = [2 => 'kétszer', 3 => 'háromszor', 4 => 'négyszer', 5 => 'ötször'];

    private const STEP_SAY = ['soft' => ['Most gyengén!', 'Most finoman!'], 'strong' => ['Most erősen!', 'Most nagyot!']];

    private const TOO_STRONG = [
        'feather' => 'Hoppá, elrepült a toll! Fújj sokkal finomabban, mint a szellő!',
        'bubbles' => 'Kipukkadt! Fújj lassabban, finomabban!',
        'pinwheel' => 'Ez túl erős volt! Finomabban, mint a szellő!',
    ];

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $kinds = self::KINDS[$tier];

        // tier 3: gentle and soft-and-strong rounds take turns
        $pools = collect($kinds)->map(fn ($kind) => $items->filter(fn ($i) => $i->payload['kind'] === $kind)->values())
            ->filter(fn ($pool) => $pool->isNotEmpty())->values();
        if ($pools->isEmpty()) {
            $pools = collect([$items->values()]);
        }
        $per = $pools->count();
        $streams = $pools->map(fn ($pool, $k) => $this->cycle($pool, intdiv($count - $k + $per - 1, $per))->values())->all();
        $picked = [];
        for ($r = 0; $r < $count; $r++) {
            $picked[] = $streams[$r % $per][intdiv($r, $per)];
        }

        $rounds = [];
        foreach ($picked as $r => $item) {
            $rounds[] = $this->blowRound($item, $r, $count);
        }

        return $rounds;
    }

    private function blowRound(object $item, int $r, int $count): array
    {
        $p = $item->payload;
        $scene = $p['scene'];
        $kind = $p['kind'];
        if (! in_array($scene, self::SCENES[$kind] ?? [], true)) {
            $scene = self::SCENES[$kind][0];
        }

        $data = ['mode' => 'blow', 'scene' => $scene, 'kind' => $kind];
        $hints = [
            'voiced' => 'Ne mondd, hanem fújd! Mint amikor a forró levest fújod.',
            'quiet' => 'Fújj egy kicsit erősebben, közelebb a telefonhoz!',
        ];

        switch ($kind) {
            case 'puffs':
                $n = max(2, min(5, (int) ($p['count'] ?? 3)));
                $data['target'] = ['puffs' => $n];
                $how = match ($scene) {
                    'candles' => 'Fújd el a gyertyákat egyenként! Minden rövid fújásra kialszik egy.',
                    'bubbles' => 'Fújj '.self::TIMES[$n].', röviden! Minden fújásra felszáll egy buborék.',
                    default => 'Fújj rá '.self::TIMES[$n].', röviden, és repülnek a pihék!',
                };
                $done = match ($scene) {
                    'candles' => $this->pickNot(['Mind kialudt! Ügyes vagy!', 'Elfújtad az összes gyertyát!'], null),
                    'bubbles' => 'Nézd, mennyi buborék! Ügyes vagy!',
                    default => 'Szétrepült a pitypang! Ügyes vagy!',
                };
                break;
            case 'long':
                [$from, $to] = self::HOLD_MS;
                $ms = $count > 1 ? (int) round($from + ($to - $from) * $r / ($count - 1), -2) : $from;
                $data['target'] = ['holdMs' => $ms];
                $how = match ($scene) {
                    'boat' => 'Fújj a vitorlába egy nagy, hosszú fújást, amíg a hajó partot ér!',
                    'pinwheel' => 'Fújj egy nagy, hosszú fújást, hogy a szélforgó sokáig pörögjön!',
                    default => 'Fújd el az összes pihét egyetlen hosszú fújással!',
                };
                $done = match ($scene) {
                    'boat' => 'Partot ért a hajó! Szép hosszú fújás volt!',
                    'pinwheel' => 'Hú, de sokáig pörgött a szélforgó!',
                    default => 'Egy fújással elrepült az összes pihe!',
                };
                $hints['fell'] = 'Fújj tovább, ne hagyd abba!';
                break;
            case 'gentle':
                $data['target'] = ['softMs' => self::SOFT_MS];
                $how = match ($scene) {
                    'feather' => 'Fújj nagyon finoman, hogy a toll lebegjen! Ha túl erősen fújod, elrepül.',
                    'bubbles' => 'Fújj lassan és finoman, hogy nagyra nőjön a buborék! Ha erősen fújod, kipukkad.',
                    default => 'Fújj gyengén és egyenletesen, hogy a szélforgó lassan forogjon!',
                };
                $done = match ($scene) {
                    'feather' => 'Szépen lebegett a toll! Nagyon finoman fújtál!',
                    'bubbles' => 'Milyen nagy buborék lett! Nagyon finoman fújtál!',
                    default => 'Szép lassan forgott! Nagyon finoman fújtál!',
                };
                $hints['tooStrong'] = self::TOO_STRONG[$scene];
                break;
            default: // alternate
                $n = max(2, min(6, (int) ($p['steps'] ?? 4)));
                $data['target'] = ['stepMs' => self::STEP_MS];
                $data['steps'] = collect(range(0, $n - 1))->map(fn ($i) => [
                    'strength' => $i % 2 ? 'strong' : 'soft',
                    'say' => self::STEP_SAY[$i % 2 ? 'strong' : 'soft'][intdiv($i, 2) % 2],
                ])->all();
                $how = 'Hol gyengén, hol erősen fújj, ahogy mondom!';
                $done = $scene === 'boat'
                    ? 'Gyengén is, erősen is tudsz fújni! Partot ért a hajó!'
                    : 'Hol lassan, hol gyorsan pörgött! Gyengén is, erősen is tudsz fújni!';
        }

        if (! empty($p['friend'])) {
            $data['friend'] = $p['friend'];
        }
        $data['hints'] = $hints;
        $data['onCorrect'] = trim($done.' '.($p['cheer'] ?? ''));

        return $this->round('voice', "{$p['story']} $how", $data, $item->id, [$p['story'], $how]);
    }
}

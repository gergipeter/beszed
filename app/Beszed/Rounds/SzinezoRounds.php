<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Színező: a line picture (drawn on the client, engines/color/pictures.js) coloured part by part as Csillám
 * says. Each content item is one picture and its steps ("teto=piros", in the order they matter most).
 * $level: 1 → three of the first parts, one per sentence, three paint pots; 2 → four parts, six pots;
 * 3 → two parts in one sentence, with the left/right pair of the picture ("a bal oldali ablakot kékre, a jobb
 * oldalit pedig sárgára"), eight pots. After the steps the child colours the rest freely and says "Kész".
 */
class SzinezoRounds extends RoundFactory
{
    /**
     * Every picture of the client library: region id => [name, accusative]. Keep in sync with
     * engines/color/pictures.js (SzinezoTest compares the ids). Ids ending in _bal / _jobb are a left/right pair.
     */
    public const PICTURES = [
        'haz' => [
            'nap' => ['nap', 'napot'], 'felho' => ['felhő', 'felhőt'], 'fu' => ['fű', 'füvet'], 'kemeny' => ['kémény', 'kéményt'],
            'teto' => ['tető', 'tetőt'], 'fal' => ['fal', 'falat'], 'ajto' => ['ajtó', 'ajtót'],
            'ablak_bal' => ['bal oldali ablak', 'bal oldali ablakot'], 'ablak_jobb' => ['jobb oldali ablak', 'jobb oldali ablakot'],
        ],
        'hal' => [
            'hinar_bal' => ['bal oldali hínár', 'bal oldali hínárt'], 'hinar_jobb' => ['jobb oldali hínár', 'jobb oldali hínárt'],
            'kovek' => ['kövek', 'köveket'], 'farok' => ['hal farka', 'hal farkát'], 'uszony' => ['uszonyok', 'uszonyokat'],
            'hal' => ['hal', 'halat'], 'pottyok' => ['pöttyök', 'pöttyöket'], 'buborekok' => ['buborékok', 'buborékokat'],
        ],
        'auto' => [
            'nap' => ['nap', 'napot'], 'felho' => ['felhő', 'felhőt'], 'ut' => ['út', 'utat'], 'auto' => ['autó', 'autót'],
            'ablakok' => ['ablakok', 'ablakokat'],
            'kerek_bal' => ['bal oldali kerék', 'bal oldali kereket'], 'kerek_jobb' => ['jobb oldali kerék', 'jobb oldali kereket'],
        ],
        'fa' => [
            'nap' => ['nap', 'napot'], 'fu' => ['fű', 'füvet'], 'torzs' => ['törzs', 'törzset'], 'lomb' => ['lomb', 'lombot'],
            'alma_bal' => ['bal oldali alma', 'bal oldali almát'], 'alma_jobb' => ['jobb oldali alma', 'jobb oldali almát'],
            'kosar' => ['kosár', 'kosarat'],
        ],
        'hajo' => [
            'nap' => ['nap', 'napot'], 'felho' => ['felhő', 'felhőt'], 'viz' => ['víz', 'vizet'],
            'vitorla_bal' => ['bal oldali vitorla', 'bal oldali vitorlát'], 'vitorla_jobb' => ['jobb oldali vitorla', 'jobb oldali vitorlát'],
            'zaszlo' => ['zászló', 'zászlót'], 'hajo' => ['hajó', 'hajót'],
        ],
        'sarkany' => [
            'nap' => ['nap', 'napot'],
            'felho_bal' => ['bal oldali felhő', 'bal oldali felhőt'], 'felho_jobb' => ['jobb oldali felhő', 'jobb oldali felhőt'],
            'domb' => ['domb', 'dombot'], 'sarkany' => ['sárkány', 'sárkányt'], 'masnik' => ['masnik', 'masnikat'],
        ],
        'raketa' => [
            'hold' => ['hold', 'holdat'],
            'csillag_bal' => ['bal oldali csillag', 'bal oldali csillagot'], 'csillag_jobb' => ['jobb oldali csillag', 'jobb oldali csillagot'],
            'lang' => ['láng', 'lángot'], 'szarnyak' => ['szárnyak', 'szárnyakat'], 'raketa' => ['rakéta', 'rakétát'],
            'orr' => ['rakéta orra', 'rakéta orrát'], 'ablak' => ['ablak', 'ablakot'],
        ],
        'pillango' => [
            'fu' => ['fű', 'füvet'],
            'virag_bal' => ['bal oldali virág', 'bal oldali virágot'], 'virag_jobb' => ['jobb oldali virág', 'jobb oldali virágot'],
            'kozepek' => ['virágok közepe', 'virágok közepét'], 'szarnyak' => ['szárnyak', 'szárnyakat'],
            'pottyok' => ['pöttyök', 'pöttyöket'], 'test' => ['pillangó teste', 'pillangó testét'],
        ],
        'katica' => [
            'virag_bal' => ['bal oldali virág', 'bal oldali virágot'], 'virag_jobb' => ['jobb oldali virág', 'jobb oldali virágot'],
            'kozepek' => ['virágok közepe', 'virágok közepét'], 'level' => ['levél', 'levelet'],
            'fej' => ['katica feje', 'katica fejét'], 'hat' => ['katica háta', 'katica hátát'], 'pottyok' => ['pöttyök', 'pöttyöket'],
        ],
        'vonat' => [
            'fust' => ['füst', 'füstöt'], 'sin' => ['sín', 'sínt'], 'kemeny' => ['kémény', 'kéményt'], 'kocsi' => ['kocsi', 'kocsit'],
            'mozdony' => ['mozdony', 'mozdonyt'], 'teto' => ['tető', 'tetőt'], 'ablakok' => ['ablakok', 'ablakokat'],
            'kerekek' => ['kerekek', 'kerekeket'],
        ],
        'gomba' => [
            'nap' => ['nap', 'napot'], 'fu' => ['fű', 'füvet'], 'tonk' => ['tönk', 'tönköt'], 'kalap' => ['gomba kalapja', 'gomba kalapját'],
            'pottyok' => ['pöttyök', 'pöttyöket'], 'csiga' => ['csiga', 'csigát'], 'csigahaz' => ['csigaház', 'csigaházat'],
        ],
        'hoember' => [
            'ho' => ['hó', 'havat'],
            'fenyo_bal' => ['bal oldali fenyőfa', 'bal oldali fenyőfát'], 'fenyo_jobb' => ['jobb oldali fenyőfa', 'jobb oldali fenyőfát'],
            'hoember' => ['hóember', 'hóembert'], 'orr' => ['hóember orra', 'hóember orrát'], 'sal' => ['sál', 'sálat'],
            'gombok' => ['gombok', 'gombokat'], 'kalap' => ['kalap', 'kalapot'],
        ],
        'bohoc' => [
            'haj' => ['haj', 'hajat'], 'arc' => ['arc', 'arcot'], 'kalap' => ['kalap', 'kalapot'], 'orr' => ['bohóc orra', 'bohóc orrát'],
            'szaj' => ['száj', 'szájat'], 'masni' => ['masni', 'masnit'],
        ],
        'fagyi' => [
            'ostya' => ['ostya', 'ostyát'], 'tolcser' => ['tölcsér', 'tölcsért'], 'gomboc' => ['gombóc', 'gombócot'],
            'ontet' => ['öntet', 'öntetet'], 'cseresznye' => ['cseresznye', 'cseresznyét'],
        ],
    ];

    /** The paint pots, in the paint box's order: id => [name, sublative ("pirosra")]. */
    public const COLORS = [
        'piros' => ['piros', 'pirosra'], 'narancs' => ['narancssárga', 'narancssárgára'], 'sarga' => ['sárga', 'sárgára'],
        'zold' => ['zöld', 'zöldre'], 'kek' => ['kék', 'kékre'], 'lila' => ['lila', 'lilára'], 'rozsaszin' => ['rózsaszín', 'rózsaszínre'],
        'barna' => ['barna', 'barnára'], 'szurke' => ['szürke', 'szürkére'], 'fekete' => ['fekete', 'feketére'],
    ];

    /** Pots never put next to each other at level 1, where the child is still learning the colour words. */
    private const LOOKALIKE = [
        'piros' => ['narancs', 'rozsaszin'], 'narancs' => ['piros', 'sarga', 'barna'], 'sarga' => ['narancs'],
        'kek' => ['lila'], 'lila' => ['kek', 'rozsaszin'], 'rozsaszin' => ['piros', 'lila'], 'barna' => ['narancs'],
        'szurke' => ['fekete'], 'fekete' => ['szurke'],
    ];

    private const POTS = [1 => 3, 2 => 6, 3 => 8];

    /** At least this many steps without left/right, so every level has enough to ask. */
    public const MIN_PLAIN_STEPS = 4;

    public static function isSide(string $region): bool
    {
        return (bool) preg_match('/_(bal|jobb)$/', $region);
    }

    /** "teto=piros" → ['teto', 'piros'] (null when malformed). @return array{0: string, 1: string}|null */
    public static function parseStep(string $step): ?array
    {
        $parts = array_map('trim', explode('=', $step));

        return count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '' ? [$parts[0], $parts[1]] : null;
    }

    /** ContentRules: the picture, its regions and the colours must all exist. @return array<string, string> */
    public static function contentErrors(array $p): array
    {
        $regions = self::PICTURES[$p['picture']] ?? null;
        if (! $regions) {
            return ['picture' => 'Nincs ilyen kép a rajzkönyvtárban ('.implode(', ', array_keys(self::PICTURES)).').'];
        }
        $seen = [];
        foreach ($p['steps'] as $step) {
            $parsed = self::parseStep($step);
            if (! $parsed) {
                return ['steps' => "„{$step}”: rész=szín alakban add meg (pl. teto=piros)."];
            }
            [$region, $color] = $parsed;
            if (! isset($regions[$region])) {
                return ['steps' => "„{$region}”: ez a kép nem ilyen részekből áll (".implode(', ', array_keys($regions)).').'];
            }
            if (! isset(self::COLORS[$color])) {
                return ['steps' => "„{$color}”: ismeretlen szín (".implode(', ', array_keys(self::COLORS)).').'];
            }
            if (isset($seen[$region])) {
                return ['steps' => "A(z) „{$region}” részt csak egyszer színeztesd."];
            }
            $seen[$region] = true;
        }
        $plain = collect(array_keys($seen))->reject(fn ($r) => self::isSide($r))->count();

        return $plain >= self::MIN_PLAIN_STEPS ? [] : ['steps' => 'Legalább '.self::MIN_PLAIN_STEPS.' lépés kell, ami nem bal/jobb oldali rész.'];
    }

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));
        // level 3 is about left and right: pictures with such a pair
        $pool = $level === 3 ? $items->filter(fn ($i) => $this->pairOf($this->steps($i)) !== null)->values() : $items->values();
        if ($pool->isEmpty()) {
            $pool = $items->values();
        }
        $this->favorLevel($pool, $level);
        $rounds = [];

        foreach ($this->cycle($pool, $count)->values() as $r => $item) {
            $rounds[] = $this->buildRound($item, $level, $r);
        }

        return $rounds;
    }

    /** @return list<array{0: string, 1: string}> */
    private function steps(object $item): array
    {
        return array_values(array_filter(array_map(fn ($s) => self::parseStep($s), $item->payload['steps'])));
    }

    /** The first left/right pair among the steps ([left step, right step]), or null. */
    private function pairOf(array $steps): ?array
    {
        $left = collect($steps)->first(fn ($s) => str_ends_with($s[0], '_bal'));
        if (! $left) {
            return null;
        }
        $base = substr($left[0], 0, -4);
        $right = collect($steps)->first(fn ($s) => $s[0] === "{$base}_jobb");

        return $right ? [$left, $right] : null;
    }

    private function buildRound(object $item, int $level, int $r): array
    {
        $picture = $item->payload['picture'];
        $name = $item->payload['name'];
        $regions = self::PICTURES[$picture];
        $steps = $this->steps($item);
        $plain = array_values(array_filter($steps, fn ($s) => ! self::isSide($s[0])));

        // one region per sentence (levels 1–2), or two per sentence (level 3)
        $sentences = match ($level) {
            1 => array_map(fn ($s) => [$s], $this->pickInOrder(array_slice($plain, 0, 4), 3)),
            2 => array_map(fn ($s) => [$s], $this->pickInOrder($plain, 4)),
            default => $this->twoAtATime($plain, $this->pairOf($steps)),
        };

        $used = collect($sentences)->flatten(1)->pluck(1)->unique()->values()->all();
        $pots = $this->pots($used, self::POTS[$level], $level === 1);

        $lower = mb_strtolower($name);
        $stepData = [];
        foreach ($sentences as $i => $parts) {
            $say = $this->instruction($parts, $regions);
            $stepData[] = [
                'say' => $say,
                // said when the step comes after another one
                'lead' => $i === 0 ? $say : $this->pickNot(['Szép!', 'Ügyes!', 'Így van!', 'Nagyon jó!'], null).' '.$this->next($parts, $regions, $say),
                'fills' => array_map(fn ($s) => [
                    'region' => $s[0],
                    'color' => $s[1],
                    'wrongColor' => "Hoppá, ez nem {$this->colorName($s[1])}! Válaszd a {$this->colorName($s[1])} festéket!",
                    'wrongPart' => "Hoppá, nem ott! Keresd meg {$this->acc($regions, $s[0])}, és színezd {$this->sub($s[1])}!",
                ], $parts),
            ];
        }

        $intro = $r === 0
            ? "Nézd, egy $lower! Először koppints a festékre, aztán a kép részére."
            : $this->pickNot(["Nézd, egy $lower!", "Itt egy $lower.", "Most egy $lower jön."], null);

        return $this->round('color', "$intro {$stepData[0]['say']}", [
            'picture' => $picture,
            'name' => $name,
            'regions' => array_map(fn ($pair) => $pair[0], $regions),
            'colors' => array_map(fn ($c) => $c[0], self::COLORS),
            'pots' => $pots,
            'freePots' => array_keys(self::COLORS),
            'steps' => $stepData,
            'pickFirst' => 'Előbb koppints egy festékre!',
            'free' => 'Ügyes vagy! A többit úgy színezd, ahogy szeretnéd. Ha kész vagy, koppints a pipára!',
            'onCorrect' => $this->pickNot([
                "Gyönyörű lett {$this->art($lower)} $lower!",
                "De szép {$this->art($lower)} $lower! Igazi kis festő vagy!",
                "Kész {$this->art($lower)} $lower! Nagyon szép lett!",
            ], null),
        ], $item->id);
    }

    /** $n random steps of $steps, kept in their content order. */
    private function pickInOrder(array $steps, int $n): array
    {
        if (count($steps) <= $n) {
            return $steps;
        }
        $keys = (array) array_rand($steps, $n);
        sort($keys);

        return array_map(fn ($k) => $steps[$k], $keys);
    }

    /** Level 3: two parts per sentence: two or three sentences, the left/right pair last. */
    private function twoAtATime(array $plain, ?array $pair): array
    {
        $wanted = $pair ? 2 : 3; // plain sentences besides the pair (pair + 2, or 3 without one)
        $picked = $this->pickInOrder($plain, min(count($plain) - count($plain) % 2, $wanted * 2));
        $sentences = array_chunk($picked, 2);
        if ($pair) {
            $sentences[] = $pair;
        }

        return array_values(array_slice(array_filter($sentences, fn ($s) => count($s) === 2), 0, 3));
    }

    /** "Színezd a tetőt pirosra!" / "Színezd a bal oldali ablakot kékre, a jobb oldalit pedig sárgára!" */
    private function instruction(array $parts, array $regions): string
    {
        $first = "{$this->acc($regions, $parts[0][0])} {$this->sub($parts[0][1])}";
        if (count($parts) === 1) {
            return "Színezd $first!";
        }

        return "Színezd $first, {$this->second($parts, $regions)}!";
    }

    /** A later single step said a little differently, so it doesn't sound like a machine. */
    private function next(array $parts, array $regions, string $say): string
    {
        if (count($parts) > 1) {
            return $say;
        }
        [$region, $color] = $parts[0];

        return $this->pickNot([
            "Most színezd {$this->acc($regions, $region)} {$this->sub($color)}!",
            'Most '.$this->acc($regions, $region)." színezd {$this->sub($color)}!",
        ], null);
    }

    /** The second half: "az ajtót pedig barnára", "a jobb oldalit is kékre". */
    private function second(array $parts, array $regions): string
    {
        [[$r1, $c1], [$r2, $c2]] = $parts;
        $same = $c1 === $c2 ? 'is' : 'pedig';
        $pairBase = self::isSide($r1) && self::isSide($r2) && preg_replace('/_(bal|jobb)$/', '', $r1) === preg_replace('/_(bal|jobb)$/', '', $r2);
        $object = $pairBase ? (str_ends_with($r2, '_jobb') ? 'a jobb oldalit' : 'a bal oldalit') : $this->acc($regions, $r2);

        return "$object $same {$this->sub($c2)}";
    }

    private function acc(array $regions, string $region): string
    {
        $acc = $regions[$region][1];

        return "{$this->art($acc)} $acc";
    }

    private function sub(string $color): string
    {
        return self::COLORS[$color][1];
    }

    private function colorName(string $color): string
    {
        return self::COLORS[$color][0];
    }

    /**
     * The needed colours plus others up to $total, in paint-box order. At level 1 the extra pots are
     * never look-alikes of the needed ones (no orange next to red).
     */
    private function pots(array $needed, int $total, bool $distinct): array
    {
        $avoid = $distinct ? collect($needed)->flatMap(fn ($c) => self::LOOKALIKE[$c] ?? [])->all() : [];
        $others = collect(array_keys(self::COLORS))->diff($needed)->shuffle();
        $extra = $others->reject(fn ($c) => in_array($c, $avoid, true))->merge($others->filter(fn ($c) => in_array($c, $avoid, true)));
        $chosen = array_merge($needed, $extra->take(max(0, $total - count($needed)))->all());

        return array_values(array_filter(array_keys(self::COLORS), fn ($c) => in_array($c, $chosen, true)));
    }
}

<?php

namespace App\Beszed;

use App\Beszed\Content\Hungarian;
use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use App\Models\Child;
use Carbon\CarbonImmutable;

/**
 * How a child does on single sounds, not just on whole games: every answer stores the
 * content item it was about, and for some games that item names a sound.
 *
 *  - kezdo       first sound of the word            -> kind "start",    label "s"
 *  - rimelo      rhyme ending                       -> kind "rhyme",    label "-ó"
 *  - zs          zs or s?                           -> kind "contrast", label "s – zs"
 *  - ikerhangok  word pair that differs by a sound  -> kind "contrast", label "s – sz"
 *
 * (rimparok is a memory game: its answers are not about one item, so it has no sound.)
 * Contrasts are pooled across games, so the two games that practise "s – zs" show as
 * one row. Same first-try rule, trend and bands as ProgressReport; a sound with fewer
 * answers than `beszed_skills.min_answers` in the period is left out (too noisy to say
 * anything), but its answers still count in `attempts`.
 */
class SoundProgress
{
    /** @var array<string, string> game => kind of sound its items name */
    public const GAMES = ['kezdo' => 'start', 'zs' => 'contrast', 'rimelo' => 'rhyme', 'ikerhangok' => 'contrast'];

    private const KIND_ORDER = ['start', 'contrast', 'rhyme'];

    private const EXAMPLES = 3;

    /**
     * @return array{
     *     minAttempts: int, attempts: int, few: int,
     *     strongest: ?string, weakest: ?string, improved: ?string,
     *     items: list<array<string, mixed>>
     * }
     */
    public function for(Child $child, int $days = 30, ?CarbonImmutable $since = null): array
    {
        $days = max(1, min($days, 365));
        $since ??= CarbonImmutable::now()->subDays($days);
        $cfg = config('beszed_skills');
        $min = (int) $cfg['min_answers'];

        $groups = $this->groups($child, $since, $since->subDays($days));

        $rate = fn (int $n, int $firstTry): ?float => $n >= $min ? round($firstTry / $n, 2) : null;

        $items = [];
        foreach ($groups as $key => $g) {
            if ($g['now'] < $min) {
                continue;
            }
            $now = $rate($g['now'], $g['nowFirst']);
            $before = $rate($g['before'], $g['beforeFirst']);
            arsort($g['games']);
            arsort($g['examples']);

            $items[] = [
                'key' => $key,
                'kind' => $g['kind'],
                'label' => $g['label'],
                'games' => array_keys($g['games']),
                'examples' => array_slice(array_keys($g['examples']), 0, self::EXAMPLES),
                'attempts' => $g['now'],
                'firstTryRate' => $now,
                'previousRate' => $before,
                'trend' => ProgressReport::trend($now, $before, $cfg['trend_delta']),
                'band' => ProgressReport::band($now, $cfg['bands']),
                'overallAttempts' => $g['all'],
                'overallRate' => $rate($g['all'], $g['allFirst']),
            ];
        }

        usort($items, fn ($a, $b) => [array_search($a['kind'], self::KIND_ORDER), -$a['attempts'], $a['label']]
            <=> [array_search($b['kind'], self::KIND_ORDER), -$b['attempts'], $b['label']]);

        return [
            'minAttempts' => $min,
            'attempts' => (int) array_sum(array_column($groups, 'now')),
            'few' => count(array_filter($groups, fn ($g) => $g['now'] > 0 && $g['now'] < $min)),
        ] + $this->highlights($items);
    }

    /**
     * One row per sound / contrast: answers this period, in the period before and
     * ever, first-try ones among them. Grouped in SQL per content item (a few hundred
     * rows at most), then folded by sound once the payloads say which sound each is.
     *
     * @return array<string, array<string, mixed>>
     */
    private function groups(Child $child, CarbonImmutable $since, CarbonImmutable $from): array
    {
        $first = 'correct AND tries = 1';
        [$s, $f] = [$since->toDateTimeString(), $from->toDateTimeString()];

        $rows = BeszedAttempt::query()
            ->where('child_id', $child->id)
            ->whereIn('game', array_keys(self::GAMES))
            ->whereNotNull('content_item_id')
            ->groupBy('game', 'content_item_id')
            ->selectRaw(
                'game, content_item_id, COUNT(*) as ever, '
                ."SUM(CASE WHEN $first THEN 1 ELSE 0 END) as ever_first, "
                .'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as now_n, '
                ."SUM(CASE WHEN created_at >= ? AND $first THEN 1 ELSE 0 END) as now_first, "
                .'SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as before_n, '
                ."SUM(CASE WHEN created_at >= ? AND created_at < ? AND $first THEN 1 ELSE 0 END) as before_first",
                [$s, $s, $f, $s, $f, $s])
            ->get();

        $payloads = BeszedContentItem::whereIn('id', $rows->pluck('content_item_id'))->pluck('payload', 'id');

        $groups = [];
        foreach ($rows as $r) {
            $named = $this->name($r->game, (array) ($payloads[$r->content_item_id] ?? []));
            if (! $named) {
                continue;
            }
            [$kind, $label, $example] = $named;

            $g = &$groups["$kind:$label"];
            $g ??= ['kind' => $kind, 'label' => $label, 'games' => [], 'examples' => [], 'now' => 0, 'nowFirst' => 0,
                'before' => 0, 'beforeFirst' => 0, 'all' => 0, 'allFirst' => 0];
            $g['now'] += (int) $r->now_n;
            $g['nowFirst'] += (int) $r->now_first;
            $g['before'] += (int) $r->before_n;
            $g['beforeFirst'] += (int) $r->before_first;
            $g['all'] += (int) $r->ever;
            $g['allFirst'] += (int) $r->ever_first;
            $g['games'][$r->game] = ($g['games'][$r->game] ?? 0) + (int) $r->now_n;
            if ($example !== null) {
                $g['examples'][$example] = ($g['examples'][$example] ?? 0) + (int) $r->now_n;
            }
            unset($g);
        }

        return $groups;
    }

    /**
     * The sound an item is about: [kind, label, example to show] or null when it names none.
     *
     * @return array{0: string, 1: string, 2: ?string}|null
     */
    private function name(string $game, array $p): ?array
    {
        $text = fn (string $key): ?string => is_string($p[$key] ?? null) && trim($p[$key]) !== '' ? mb_strtolower(trim($p[$key])) : null;

        return match ($game) {
            'kezdo' => ($sound = $text('sound')) ? ['start', $sound, $text('word')] : null,
            'rimelo' => ($rhyme = $text('rhyme')) ? ['rhyme', '-'.ltrim($rhyme, '-'), $text('word')] : null,
            'zs' => ['contrast', $this->pair('s', 'zs'), null],
            'ikerhangok' => ($contrast = $this->contrast($p)) ? ['contrast', $contrast, $text('wordA') && $text('wordB') ? $text('wordA').' – '.$text('wordB') : null] : null,
            default => null,
        };
    }

    /**
     * "s – sz" for a word pair: the content's own `contrast` when it has one, otherwise
     * worked out from the words. Only real minimal pairs have one: kéz / kés (z – s),
     * só / szó (s – sz), hal / hall (l – ll); pite / bili or toll / doboz differ in more
     * than one sound and are left out. Either way the two sides come out in a fixed
     * order, so "sz – s" and "s – sz" are one row.
     */
    private function contrast(array $p): ?string
    {
        if (is_string($p['contrast'] ?? null) && trim($p['contrast']) !== '') {
            $parts = array_values(array_filter(preg_split('/\s*[–—-]\s*/u', mb_strtolower(trim($p['contrast']))), 'strlen'));

            return count($parts) === 2 ? $this->pair($parts[0], $parts[1]) : mb_strtolower(trim($p['contrast']));
        }
        if (! is_string($p['wordA'] ?? null) || ! is_string($p['wordB'] ?? null)) {
            return null;
        }

        $a = array_column(Hungarian::letters($p['wordA']), 'letter');
        $b = array_column(Hungarian::letters($p['wordB']), 'letter');

        if (count($a) === count($b)) {
            $at = array_keys(array_diff_assoc($a, $b));

            return count($at) === 1 ? $this->pair($a[$at[0]], $b[$at[0]]) : null;
        }
        if (abs(count($a) - count($b)) !== 1) {
            return null;
        }

        // One word has a letter the other has once: a long consonant (hal / hall).
        [$short, $long] = count($a) < count($b) ? [$a, $b] : [$b, $a];
        foreach ($long as $i => $letter) {
            $without = $long;
            array_splice($without, $i, 1);
            if ($without === $short && ($letter === ($long[$i - 1] ?? null) || $letter === ($long[$i + 1] ?? null))) {
                return $this->pair($letter, $letter.$letter);
            }
        }

        return null;
    }

    /** "s – sz": the two sounds in alphabetical order (long and short vowels next to each other). */
    private function pair(string $a, string $b): string
    {
        $sides = [mb_strtolower(trim($a)), mb_strtolower(trim($b))];
        usort($sides, fn ($x, $y) => [Hungarian::fold($x), $x] <=> [Hungarian::fold($y), $y]);

        return implode(' – ', $sides);
    }

    /**
     * Which sound went best, which most needs practice and which improved most. Only
     * named when they say something: a "best" needs another sound to be better than,
     * a "weakest" is never one the child already has firmly (band "strong") and, with
     * several sounds, stands out from the rest unless they are all still in the practice
     * band, and a "best" is never one that is itself still in the practice band.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{strongest: ?string, weakest: ?string, improved: ?string}
     */
    private function highlights(array $items): array
    {
        $rated = array_values(array_filter($items, fn ($i) => $i['firstTryRate'] !== null));
        // Best first; more answers break a tie.
        usort($rated, fn ($a, $b) => [-$a['firstTryRate'], -$a['attempts']] <=> [-$b['firstTryRate'], -$b['attempts']]);

        $best = $rated[0] ?? null;
        $worst = $rated ? $rated[count($rated) - 1] : null;
        $many = count($rated) >= 2 && $best['firstTryRate'] > $worst['firstTryRate'];
        $toPractise = $worst && $worst['band'] !== 'strong' && (count($rated) === 1 || $many || $worst['band'] === 'practice');

        $up = array_filter($rated, fn ($i) => $i['trend'] === 'up');
        usort($up, fn ($a, $b) => [-round($a['firstTryRate'] - $a['previousRate'], 2), -$a['attempts']]
            <=> [-round($b['firstTryRate'] - $b['previousRate'], 2), -$b['attempts']]);

        return [
            'strongest' => $many && $best['band'] !== 'practice' ? $best['key'] : null,
            'weakest' => $toPractise ? $worst['key'] : null,
            'improved' => $up[0]['key'] ?? null,
            'items' => $items,
        ];
    }
}

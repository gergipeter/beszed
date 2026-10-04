<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Mozgó szavak (verbs). Level 1: "Ki fut?" → three action pictures.
 * Level 2: four pictures, alternating with "Mit csinál?" where the child
 * listens to three spoken verbs and picks the right one. Level 3: verb
 * endings — "Ő úszik. Én is …" → úszom / úszol / úsznak (the forms come
 * from the content, checked by hand: ikes verbs say eszem, not eszek).
 *
 * Distractors never also fit: animal voices (ugat) only with each other,
 * never the same picture, and never a verb the content marks as close
 * (úszik ↔ fürdik, alszik ↔ ásít).
 */
class IgekRounds extends RoundFactory
{
    /** Persons of the level-3 forms, in the order the content stores them. */
    public const PERSONS = ['en' => 'Én', 'te' => 'Te', 'mi' => 'Mi', 'ok' => 'Ők'];

    /** Each spoken option is a little loudspeaker: tap to hear it. */
    public const SPEAKER = '🔊';

    private const LISTEN = 'Koppints a hangszórókra, és hallgasd meg őket! A jó alatt nyomd meg a felfelé mutató ujjat!';

    private bool $explained = false;

    public function build(Collection $items, int $level, int $count): array
    {
        $this->explained = false;
        $this->favorLevel($items, $level);
        $withForms = $items->filter(fn ($i) => count($i->payload['forms'] ?? []) === 4)->values();
        $pool = $level >= 3 && $withForms->isNotEmpty() ? $withForms : $items->values();
        $lastPerson = null;
        $rounds = [];

        foreach ($this->cycle($pool, $count)->values() as $r => $item) {
            if ($level >= 3 && $r % 4 !== 3 && count($item->payload['forms'] ?? []) === 4) {
                $lastPerson = $this->pickNot(array_keys(self::PERSONS), $lastPerson);
                $rounds[] = $this->ending($item, $lastPerson);
            } elseif ($level === 2 && $r % 2 === 1) {
                $rounds[] = $this->whatDoes($items, $item);
            } else {
                $rounds[] = $this->who($items, $item, $level === 1 ? 3 : 4);
            }
        }

        return $rounds;
    }

    /** "Ki fut?" → the child taps the runner. */
    private function who(Collection $items, $item, int $n): array
    {
        $p = $item->payload;
        $options = $this->others($items, $item, $n - 1, $n > 3)->push($item)->shuffle()->values();

        return $this->round('choice', $p['question'], [
            'layout' => $n === 4 ? 'four' : 'three',
            'options' => $options->map(fn ($o) => ['id' => (string) $o->id, 'emoji' => $o->payload['emoji']])->all(),
            'answer' => (string) $item->id,
            'onCorrect' => "Igen! {$this->ucfirst($this->subject($p))} {$p['verb']}.",
            'onWrong' => $options->mapWithKeys(fn ($o) => [
                (string) $o->id => "{$this->ucfirst($this->subject($o->payload))} {$o->payload['verb']}. {$p['question']}",
            ])->all(),
        ], $item->id);
    }

    /** The picture → "Mit csinál?": three verbs said aloud. */
    private function whatDoes(Collection $items, $item): array
    {
        $p = $item->payload;
        $subject = $this->subject($p);
        $options = $this->others($items, $item, 2, true)->push($item)->shuffle()->values();
        $ask = isset($p['who']) ? "Mit csinál {$p['who']}?" : 'Mit csinál?';

        return $this->round('choice', $this->explainOnce($ask), [
            'variant' => 'speakers',
            'layout' => 'three',
            'stimulus' => ['emoji' => $p['emoji']],
            'options' => $options->map(fn ($o) => [
                'id' => (string) $o->id, 'emoji' => self::SPEAKER, 'label' => $o->payload['verb'], 'say' => "{$this->ucfirst($o->payload['verb'])}.",
            ])->all(),
            'answer' => (string) $item->id,
            'onCorrect' => "Igen! {$this->ucfirst($subject)} {$p['verb']}.",
            'onWrong' => $options->mapWithKeys(fn ($o) => [
                (string) $o->id => "Nem, {$subject} nem {$o->payload['verb']}. $ask",
            ])->all(),
        ], $item->id);
    }

    /** "Ő úszik. Én is …" → úszom among the forms of other persons. */
    private function ending($item, string $person): array
    {
        $p = $item->payload;
        $forms = array_combine(array_keys(self::PERSONS), $p['forms']);
        $wrong = collect($forms)->except($person)->keys()->shuffle()->take(2);
        $keys = $wrong->push($person)->shuffle()->values();
        $who = self::PERSONS[$person];
        $line = fn (string $k) => self::PERSONS[$k].' is '.$forms[$k];

        return $this->round('choice', $this->explainOnce("{$this->ucfirst($this->subject($p))} {$p['verb']}. Hogy mondjuk szépen? $who is …"), [
            'variant' => 'speakers',
            'layout' => 'three',
            'stimulus' => ['emoji' => $p['emoji']],
            'options' => $keys->map(fn ($k) => [
                'id' => $k, 'emoji' => self::SPEAKER, 'label' => $forms[$k], 'say' => "$who is {$forms[$k]}.",
            ])->all(),
            'answer' => $person,
            'onCorrect' => "Igen! {$line($person)}.",
            'onWrong' => $keys->mapWithKeys(fn ($k) => [
                $k => 'Ezt így mondjuk: '.mb_strtolower($line($k)).". De most: $who is …?",
            ])->all(),
        ], $item->id);
    }

    /** Up to $n other verbs whose pictures can't also be the answer; of the same group when there are enough. */
    private function others(Collection $items, $item, int $n, bool $sameGroup): Collection
    {
        $p = $item->payload;
        $animal = $p['group'] === 'allat';
        $ok = $items->reject(fn ($o) => $o->id === $item->id
            || $o->payload['emoji'] === $p['emoji']
            || ($o->payload['group'] === 'allat') !== $animal
            || in_array($o->payload['verb'], $p['close'] ?? [], true)
            || in_array($p['verb'], $o->payload['close'] ?? [], true));
        $same = $ok->filter(fn ($o) => $o->payload['group'] === $p['group']);
        $from = $sameGroup && $same->count() >= $n ? $same : $ok;
        $picked = collect();
        foreach ($from->shuffle() as $o) {
            if ($picked->count() === $n) {
                break;
            }
            // two pictures that are each other's close verbs would make the question unfair for neither, but look alike
            if ($picked->contains(fn ($x) => $x->payload['emoji'] === $o->payload['emoji']
                || in_array($o->payload['verb'], $x->payload['close'] ?? [], true)
                || in_array($x->payload['verb'], $o->payload['close'] ?? [], true))) {
                continue;
            }
            $picked->push($o);
        }

        return $picked;
    }

    /** "a kutya" from the content, otherwise "ő". */
    private function subject(array $p): string
    {
        return $p['who'] ?? 'ő';
    }

    /** The first spoken-options round says how to listen and pick. */
    private function explainOnce(string $prompt): string
    {
        if ($this->explained) {
            return $prompt;
        }
        $this->explained = true;

        return self::LISTEN.' '.$prompt;
    }

    /**
     * Content rule: forms are én, te, mi, ők of this verb — they start like the verb, end like those persons
     * (-k/-m, -sz/-l, -unk/-ünk, -nak/-nek), and an -ik verb says the én form with -m (eszem, úszom).
     *
     * @return array<string, string>
     */
    public static function check(array $p): array
    {
        if (in_array($p['verb'], $p['close'] ?? [], true)) {
            return ['close' => 'Egy ige nem lehet önmaga párja.'];
        }
        $forms = $p['forms'] ?? [];
        if (! $forms) {
            return [];
        }
        $verb = mb_strtolower(trim($p['verb']));
        $stem = mb_substr(\App\Beszed\Content\Hungarian::fold($verb), 0, 2);
        $ends = ['/(k|m)$/u', '/(sz|l)$/u', '/(unk|ünk)$/u', '/(nak|nek)$/u'];

        return match (true) {
            str_contains($verb, ' ') => ['forms' => 'Csak egyszavas igének legyenek alakjai.'],
            count($forms) !== 4 || count(array_unique($forms)) !== 4 => ['forms' => 'Négy különböző alak kell: én, te, mi, ők.'],
            collect($forms)->contains(fn ($f) => mb_substr(\App\Beszed\Content\Hungarian::fold($f), 0, 2) !== $stem) => ['forms' => 'Az alakok ugyanúgy kezdődjenek, mint az ige.'],
            collect($forms)->contains(fn ($f, $i) => ! preg_match($ends[$i], mb_strtolower($f))) => ['forms' => 'Sorrend: én (-k/-m), te (-sz/-l), mi (-unk/-ünk), ők (-nak/-nek).'],
            str_ends_with($verb, 'ik') && ! str_ends_with($forms[0], 'm') => ['forms' => 'Ikes ige: én eszem, úszom (nem eszek).'],
            default => [],
        };
    }
}

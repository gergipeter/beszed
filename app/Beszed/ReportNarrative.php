<?php

namespace App\Beszed;

use App\Beszed\Content\Hungarian;

/**
 * Turns a ProgressReport's `areas` into a short narrative + recommendations for
 * the printable/PDF summary. Deliberately non-diagnostic: the app has no peer
 * or age norms, so wording stays in terms of "practiced well" / "still practicing",
 * never "age-appropriate" or "compared to peers".
 */
class ReportNarrative
{
    public const BAND_LABEL = [
        'strong' => 'Biztosan megy',
        'growing' => 'Fejlődik',
        'practice' => 'Gyakoroljuk még',
        'noData' => 'Még kevés adat',
    ];

    /** @param  array<int, array{key:string,label:string,difer:bool,sessions:int,firstTryRate:?float,band:string}>  $areas */
    public function narrative(array $areas): string
    {
        $withData = array_values(array_filter($areas, fn ($a) => $a['band'] !== 'noData'));

        if (! $withData) {
            return 'A vizsgált időszakban még nem gyűlt össze elég játékadat a részletes kiértékeléshez. '
                .'Javasoljuk a program rendszeres, heti több alkalommal történő használatát, hogy megbízható kép '
                .'rajzolódjon ki a gyermek fejlődéséről.';
        }

        $labelsOf = fn (string $band) => implode(', ', array_map(fn ($a) => $a['label'], array_filter($withData, fn ($a) => $a['band'] === $band)));

        $strong = $labelsOf('strong');
        $growing = $labelsOf('growing');
        $practice = $labelsOf('practice');

        $parts = [];
        if ($strong !== '') {
            $parts[] = "A gyermek biztosan teljesíti a következő területekhez tartozó feladatokat: {$strong}.";
        }
        if ($growing !== '') {
            $parts[] = "Fejlődés figyelhető meg a következő terület(ek)en: {$growing}.";
        }
        if ($practice !== '') {
            $practiceAreas = array_filter($withData, fn ($a) => $a['band'] === 'practice');
            $plural = count($practiceAreas) > 1 ? 'ek' : '';
            $parts[] = "Több gyakorlást igényelhet a következő terület{$plural}: {$practice}. "
                .'Ez önmagában nem jelent problémát, de érdemes megbeszélni logopédussal, gyógypedagógussal '
                .'vagy gyermekpszichológussal, ha a nehézség hosszabb ideig fennáll.';
        }

        return implode(' ', $parts);
    }

    /** @param  array<int, array{key:string,label:string,difer:bool,sessions:int,firstTryRate:?float,band:string}>  $areas */
    public function recommendations(array $areas): array
    {
        $practice = array_values(array_filter($areas, fn ($a) => $a['band'] === 'practice'));
        $noData = array_values(array_filter($areas, fn ($a) => $a['band'] === 'noData'));

        $items = [];
        foreach ($practice as $a) {
            $items[] = "Iktassunk be heti több rövid gyakorlást a \"{$a['label']}\" területéhez tartozó játékokból.";
        }
        if ($practice) {
            $items[] = 'Ha a nehézség 4–6 hét gyakorlás után is fennáll, javasolt szakember '
                .'(logopédus, gyógypedagógus vagy gyermekpszichológus) felkeresése.';
        }
        if ($noData) {
            $labels = implode(', ', array_map(fn ($a) => $a['label'], $noData));
            $items[] = "Próbáljuk ki a következő területek játékait is a teljesebb kép érdekében: {$labels}.";
        }
        if (! $items) {
            $items[] = 'A gyermek egyenletesen és jól teljesít minden mért területen; javasolt a jelenlegi gyakorisággal folytatni a játékot.';
        }
        $items[] = 'Ezt az összefoglalót érdemes elhozni a következő szűrővizsgálatra vagy szakemberi konzultációra.';

        return $items;
    }

    /**
     * What a ProgressReport's `sounds` say in a few sentences (which sound improved
     * most, which goes best, which to practise a little more), and one thing to do at
     * home about the one to practise. Null where there is nothing to say.
     *
     * A sound is never given a suffix itself (s-sel, sz-szel, zs-vel all differ): it
     * stands in quotes after its article (Hungarian::letterArticle: "az s", "a k") and
     * the noun after it takes the ending ("az „s” kezdőhangnál", "az „s – sz” hangpárnál").
     *
     * @param  array{items: list<array<string, mixed>>, strongest: ?string, weakest: ?string, improved: ?string}  $sounds
     * @return array{summary: ?string, tip: ?string}
     */
    public function sounds(array $sounds): array
    {
        $byKey = collect($sounds['items'])->keyBy('key');
        $pick = fn (?string $key) => $key === null ? null : $byKey->get($key);
        [$best, $weak, $up] = [$pick($sounds['strongest']), $pick($sounds['weakest']), $pick($sounds['improved'])];
        $pct = fn (float $rate) => (int) round($rate * 100);

        $parts = [];
        if ($up) {
            $parts[] = sprintf('Ebben az időszakban %s fejlődött a legtöbbet a gyermek: az elsőre jó válaszok aránya %d%%-ról %d%%-ra nőtt.',
                $this->soundPhrase($up, 'at'), $pct($up['previousRate']), $pct($up['firstTryRate']));
        }
        if ($best && $best['key'] !== ($up['key'] ?? null)) {
            $parts[] = sprintf('Legbiztosabban %s megy: az elsőre jó válaszok aránya %d%%.',
                $this->soundPhrase($best, 'at'), $pct($best['firstTryRate']));
        }
        if ($weak) {
            $parts[] = sprintf('Még érdemes egy kicsit gyakorolni %s (elsőre jó: %d%%).',
                $this->soundPhrase($weak, 'acc'), $pct($weak['firstTryRate']));
        }

        return ['summary' => $parts ? implode(' ', $parts) : null, 'tip' => $weak ? $this->soundTip($weak) : null];
    }

    /** "az „s” kezdőhangnál" (at) or "az „s” kezdőhangot" (acc). */
    private function soundPhrase(array $sound, string $case): string
    {
        $name = Hungarian::letterArticle($sound['label'])." „{$sound['label']}”";

        return match ($sound['kind']) {
            'start' => "$name ".($case === 'at' ? 'kezdőhangnál' : 'kezdőhangot'),
            'rhyme' => "$name végű ".($case === 'at' ? 'rímeknél' : 'rímeket'),
            default => "$name ".($case === 'at' ? 'hangpárnál' : 'hangpárt'),
        };
    }

    /** One short, concrete thing to do at home, by the game the sound is mostly practised in. */
    private function soundTip(array $sound): ?string
    {
        $examples = $sound['examples'];
        $e = fn (int $from, int $n) => array_slice($examples, $from, $n);
        $for = fn (array $words) => $words ? ' (például: '.implode(', ', $words).')' : '';

        return match ($sound['games'][0] ?? null) {
            'kezdo' => "Keressetek otthon három dolgot, aminek a neve ezzel a hanggal kezdődik: {$sound['label']}{$for($e(0, 2))}.",
            'zs' => 'Keressetek otthon olyan szavakat, amelyekben zümmögő zs hallatszik (például: zsiráf, rúzs), '
                .'és olyanokat, amelyekben csendes s (például: sajt, hús).',
            'ikerhangok' => $examples
                ? 'Mondjátok ki egymásnak lassan ezeket a szópárokat, és találja ki a másik, melyiket hallotta: '.implode('; ', $e(0, 2)).'.'
                : "Találjatok ki otthon szópárokat, amelyek csak egy hangban különböznek ({$sound['label']}), mondjátok ki őket lassan, és találja ki a másik, melyiket hallotta.",
            'rimelo' => "Rímjáték otthon: mondjatok egy szót{$for($e(0, 1))}, és keressetek hozzá együtt három rímelő szót{$for($e(1, 2))}.",
            default => null,
        };
    }
}

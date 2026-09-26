<?php

namespace App\Beszed;

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
}

<?php

namespace App\Beszed;

/**
 * "Csillám makes mistakes": now and then, in a picture-choice round, Csillám
 * has a go first. She points at an answer and asks the child if she's right;
 * half the time she's wrong on purpose. Catching a grown-up's mistake is a
 * joy for a small child, and saying why makes them think about the answer.
 *
 * Adds `data.guess` to a few choice rounds of a session (never the first, not
 * the "who says it right" rounds, at most `max`), with every sentence Csillám
 * says in it; ChoiceEngine plays it. The rest of the round is unchanged.
 */
final class CsillamGuess
{
    private const ASK = ['Szerintem ez az. Igazam van?', 'Hmm, én erre gondolok. Jól gondolom?', 'Szerintem ez a jó. Te mit gondolsz?'];

    /**
     * @param  array<int, array<string, mixed>>  $rounds
     * @param  array{chance?: float, max?: int}  $options
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $rounds, array $options = []): array
    {
        $chance = $options['chance'] ?? 0.25;
        $left = $options['max'] ?? 2;

        foreach ($rounds as $i => $round) {
            if ($left === 0) {
                break;
            }
            if ($i === 0 || ! self::eligible($round) || mt_rand() / mt_getrandmax() >= $chance) {
                continue;
            }
            $answer = $round['data']['answer'];
            $wrong = collect($round['data']['options'])->pluck('id')->reject(fn ($id) => $id === $answer)->values();
            $right = $wrong->isEmpty() || random_int(0, 1) === 1;

            $rounds[$i]['data']['guess'] = [
                'id' => $right ? $answer : $wrong->random(),
                'ask' => self::ASK[array_rand(self::ASK)],
                // the child agrees with her right guess / catches her wrong one
                'confirmed' => 'Hurrá, jól gondoltam! Köszönöm, hogy segítettél!',
                'caught' => 'Jaj, tényleg tévedtem! Köszönöm, hogy szóltál! Melyik a jó?',
                // the child agrees with a wrong guess / says no to a right one
                'agreedWrong' => 'Hmm, biztos? Nézd meg jól még egyszer!',
                'deniedRight' => 'Pedig szerintem ez a jó. Nézd meg még egyszer!',
            ];
            $left--;
        }

        return $rounds;
    }

    /** A choice round with one right option among several, not Brumi and Nyuszi's sentences. */
    private static function eligible(array $round): bool
    {
        $data = $round['data'] ?? [];

        return ($round['engine'] ?? null) === 'choice'
            && isset($data['answer'])
            && count($data['options'] ?? []) >= 2
            && ($data['variant'] ?? null) !== 'speakers';
    }
}

<?php

use App\Beszed\Content\Hungarian;
use App\Beszed\ProgressReport;
use App\Beszed\Reports\WeeklyReport;
use App\Beszed\ReportNarrative;
use App\Beszed\SoundProgress;
use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use App\Models\BeszedSession;
use App\Models\Child;
use App\Models\User;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé', 'birth_date' => CarbonImmutable::now()->subYears(5)->subMonth()]);
    // 30 days back from here is 24 Aug; the 30 days before that start on 25 Jul.
    travelTo(CarbonImmutable::parse('2026-09-23 12:00'));
});

function soundItem(string $game, array $payload): BeszedContentItem
{
    return BeszedContentItem::create(['game' => $game, 'level' => 1, 'payload' => $payload]);
}

/**
 * One answer per letter: y = right first time, r = right on the second try, n = wrong.
 * $ago = days before "now" (travelTo above).
 */
function soundAnswers(Child $child, BeszedContentItem $item, string $outcomes, int $ago = 3): void
{
    foreach (str_split($outcomes) as $o) {
        $a = new BeszedAttempt([
            'child_id' => $child->id, 'game' => $item->game, 'content_item_id' => $item->id, 'level' => 1,
            'correct' => $o !== 'n', 'tries' => match ($o) { 'y' => 1, 'r' => 2, default => 3 },
        ]);
        $a->created_at = now()->subDays($ago);
        $a->save();
    }
}

function soundsOf(Child $child, int $days = 30): array
{
    return app(SoundProgress::class)->for($child, $days);
}

function soundRow(array $sounds, string $key): ?array
{
    return collect($sounds['items'])->firstWhere('key', $key);
}

it('counts first-try answers per sound this period, the one before, and since the beginning', function () {
    $sajt = soundItem('kezdo', ['sound' => 's', 'word' => 'sajt', 'emoji' => '🧀']);
    $sapka = soundItem('kezdo', ['sound' => 's', 'word' => 'sapka', 'emoji' => '🧢']);

    soundAnswers($this->child, $sajt, 'yyyyyr');    // this period: 6 answers, 5 first-try
    soundAnswers($this->child, $sapka, 'yyyynn');   // 6 answers, 4 first-try
    soundAnswers($this->child, $sajt, 'yyrn', 40);  // the period before: 4 answers, 2 first-try
    soundAnswers($this->child, $sapka, 'ynn', 45);  // 3 answers, 1 first-try
    soundAnswers($this->child, $sajt, 'yyyyyyyy', 120); // long ago: 8, all first-try

    $s = soundRow(soundsOf($this->child), 'start:s');

    expect($s)->toMatchArray([
        'kind' => 'start', 'label' => 's', 'games' => ['kezdo'],
        'attempts' => 12, 'firstTryRate' => 0.75,        // 9 of 12; a right answer on the 2nd try is not a first-try one
        'previousRate' => 0.43, 'trend' => 'up',          // 3 of 7 before, 9 of 12 now
        'band' => 'strong',
        'overallAttempts' => 27, 'overallRate' => 0.74,   // 20 of 27, ever
    ])->and($s['examples'])->toEqualCanonicalizing(['sajt', 'sapka']);
});

it('uses the same first-try rate as the game and area report', function () {
    foreach (['sajt', 'sapka', 'sál'] as $word) {
        soundAnswers($this->child, soundItem('kezdo', ['sound' => 's', 'word' => $word, 'emoji' => '🧀']), 'yyrnyy');
    }

    $report = app(ProgressReport::class)->for($this->child, 30);

    expect(collect($report['games'])->firstWhere('id', 'kezdo')['firstTryRate'])->toBe(0.67)
        ->and($report['sounds']['items'][0]['firstTryRate'])->toBe(0.67)
        ->and($report['sounds']['items'][0]['attempts'])->toBe(18);
});

it('leaves out sounds with fewer than five answers this period, but still counts them', function () {
    soundAnswers($this->child, soundItem('kezdo', ['sound' => 'k', 'word' => 'kutya', 'emoji' => '🐶']), 'yyyn');   // 4: too few
    soundAnswers($this->child, soundItem('kezdo', ['sound' => 'm', 'word' => 'majom', 'emoji' => '🐒']), 'rrnnn');  // 5: enough
    soundAnswers($this->child, soundItem('kezdo', ['sound' => 'h', 'word' => 'hal', 'emoji' => '🐟']), 'yyyyyyyy', 60); // only before this period

    $sounds = soundsOf($this->child);

    expect(collect($sounds['items'])->pluck('key')->all())->toBe(['start:m'])
        ->and($sounds['attempts'])->toBe(9)          // 4 + 5; the old ones are not this period's
        ->and($sounds['few'])->toBe(1)
        ->and($sounds['minAttempts'])->toBe(5)
        ->and(soundRow($sounds, 'start:m')['firstTryRate'])->toBe(0.0);
});

it('has no previous rate or trend until the period before had five answers too', function () {
    $item = soundItem('kezdo', ['sound' => 'l', 'word' => 'labda', 'emoji' => '⚽']);
    soundAnswers($this->child, $item, 'yyyyy');
    soundAnswers($this->child, $item, 'ynnn', 40);

    expect(soundRow(soundsOf($this->child), 'start:l'))->toMatchArray(['previousRate' => null, 'trend' => null, 'overallAttempts' => 9]);

    soundAnswers($this->child, $item, 'n', 41);

    expect(soundRow(soundsOf($this->child), 'start:l'))->toMatchArray(['previousRate' => 0.2, 'trend' => 'up']);
});

it('calls a smaller change flat and a drop down', function () {
    $item = soundItem('kezdo', ['sound' => 't', 'word' => 'torta', 'emoji' => '🎂']);
    soundAnswers($this->child, $item, 'yyyyyynnnn');      // 0.6
    soundAnswers($this->child, $item, 'yyyyyyynnnnn', 40); // 7 of 12 = 0.58
    $flat = soundRow(soundsOf($this->child), 'start:t');

    $other = soundItem('kezdo', ['sound' => 'b', 'word' => 'banán', 'emoji' => '🍌']);
    soundAnswers($this->child, $other, 'yyynn');
    soundAnswers($this->child, $other, 'yyyyy', 40);

    expect($flat['trend'])->toBe('flat')
        ->and(soundRow(soundsOf($this->child), 'start:b'))->toMatchArray(['firstTryRate' => 0.6, 'previousRate' => 1.0, 'trend' => 'down']);
});

it('ignores other children, answers without a content item, and games with no sounds', function () {
    $sajt = soundItem('kezdo', ['sound' => 's', 'word' => 'sajt', 'emoji' => '🧀']);
    soundAnswers($this->child, $sajt, 'yyyyy');

    $sibling = Child::create(['user_id' => $this->user->id, 'name' => 'Bence']);
    soundAnswers($sibling, $sajt, 'nnnnnnnnnn');
    BeszedAttempt::create(['child_id' => $this->child->id, 'game' => 'kezdo', 'level' => 1, 'correct' => false, 'tries' => 3]); // no item
    soundAnswers($this->child, soundItem('hol', ['name' => 'maci', 'emoji' => '🧸']), 'yyyyyy');
    soundAnswers($this->child, soundItem('kezdo', ['word' => 'nincs hang']), 'nnnnnn'); // an item that names no sound

    $sounds = soundsOf($this->child);

    expect($sounds['items'])->toHaveCount(1)
        ->and($sounds['items'][0])->toMatchArray(['key' => 'start:s', 'attempts' => 5, 'firstTryRate' => 1.0])
        ->and($sounds['attempts'])->toBe(5);
});

it('names the rhyme ending and the zs / s contrast', function () {
    soundAnswers($this->child, soundItem('rimelo', ['word' => 'ló', 'emoji' => '🐴', 'rhyme' => 'ó']), 'yyyyyn');
    soundAnswers($this->child, soundItem('rimelo', ['word' => 'hó', 'emoji' => '❄️', 'rhyme' => 'ó']), 'yyyn');
    soundAnswers($this->child, soundItem('zs', ['word' => 'rizs', 'emoji' => '🍚', 'sound' => 'zs']), 'yyyy');
    soundAnswers($this->child, soundItem('zs', ['word' => 'hús', 'emoji' => '🍖', 'sound' => 's']), 'yyrn');

    $sounds = soundsOf($this->child);

    expect(soundRow($sounds, 'rhyme:-ó'))->toMatchArray(['kind' => 'rhyme', 'label' => '-ó', 'attempts' => 10, 'firstTryRate' => 0.8])
        ->and(soundRow($sounds, 'rhyme:-ó')['examples'])->toBe(['ló', 'hó'])
        ->and(soundRow($sounds, 'contrast:s – zs'))->toMatchArray(['kind' => 'contrast', 'games' => ['zs'], 'attempts' => 8, 'firstTryRate' => 0.75, 'examples' => []]);
});

it('works out the contrast of a word pair, and uses the content\'s own when it has one', function () {
    $pair = fn (array $p) => soundItem('ikerhangok', $p + ['emojiA' => '🅰️', 'emojiB' => '🅱️']);

    soundAnswers($this->child, $pair(['wordA' => 'só', 'wordB' => 'szó']), 'yyyyy');           // worked out: s – sz
    soundAnswers($this->child, $pair(['wordA' => 'kéz', 'wordB' => 'kés']), 'yyyyy');           // z – s, in the usual order
    soundAnswers($this->child, $pair(['wordA' => 'hal', 'wordB' => 'hall']), 'yyyyy');          // a long consonant
    soundAnswers($this->child, $pair(['wordA' => 'toll', 'wordB' => 'doboz']), 'yyyyy');        // not a minimal pair: no sound to name
    soundAnswers($this->child, $pair(['wordA' => 'vár', 'wordB' => 'váll']), 'yyyyy');          // two things differ: none either
    soundAnswers($this->child, $pair(['wordA' => 'nap', 'wordB' => 'lap', 'contrast' => 'l – n']), 'yyyyy'); // the content's own, put in order
    soundAnswers($this->child, $pair(['wordA' => 'sál', 'wordB' => 'szál', 'contrast' => 'sz – s']), 'yyyyyn'); // the other way round: same row as só / szó

    $sounds = soundsOf($this->child);
    $rows = collect($sounds['items'])->keyBy('label');

    expect($rows->keys()->sort()->values()->all())->toBe(['l – ll', 'l – n', 's – sz', 's – z'])
        ->and($rows['s – sz'])->toMatchArray(['attempts' => 11, 'firstTryRate' => 0.91, 'games' => ['ikerhangok']])
        ->and($rows['s – sz']['examples'])->toBe(['sál – szál', 'só – szó'])
        ->and($sounds['attempts'])->toBe(26); // toll / doboz and vár / váll are not counted
});

it('pools a contrast across the games that practise it', function () {
    soundAnswers($this->child, soundItem('zs', ['word' => 'rizs', 'emoji' => '🍚', 'sound' => 'zs']), 'yyyyyy');
    soundAnswers($this->child, soundItem('ikerhangok', ['wordA' => 'sál', 'emojiA' => '🧣', 'wordB' => 'zsál', 'emojiB' => '🌿', 'contrast' => 'zs – s']), 'yynnn');

    $row = soundRow(soundsOf($this->child), 'contrast:s – zs');

    expect($row)->toMatchArray(['attempts' => 11, 'firstTryRate' => 0.73, 'games' => ['zs', 'ikerhangok']]);
});

it('keeps counting answers about content that was taken out of the game', function () {
    $old = soundItem('kezdo', ['sound' => 's', 'word' => 'sajt', 'emoji' => '🧀']);
    soundAnswers($this->child, $old, 'yyyyy');
    $old->update(['active' => false]); // the seeder does this to an item that left the JSON

    expect(soundRow(soundsOf($this->child), 'start:s')['attempts'])->toBe(5);
});

it('picks the strongest, the one to practise and the biggest improvement', function () {
    $item = fn (string $sound, string $word) => soundItem('kezdo', ['sound' => $sound, 'word' => $word, 'emoji' => '🔤']);

    soundAnswers($this->child, $a = $item('m', 'majom'), 'yyyyyyyyyn');        // 0.9, was 0.4: up 0.5
    soundAnswers($this->child, $a, 'yynnnyynnn', 40);
    soundAnswers($this->child, $b = $item('sz', 'szív'), 'yyyyyyyy');        // 1.0, flat
    soundAnswers($this->child, $b, 'yyyyyyyy', 40);
    soundAnswers($this->child, $c = $item('cs', 'csiga'), 'ynnnn');           // 0.2, was 0: up 0.2
    soundAnswers($this->child, $c, 'nnnnnn', 40);

    $sounds = soundsOf($this->child);

    expect($sounds['strongest'])->toBe('start:sz')
        ->and($sounds['weakest'])->toBe('start:cs')
        ->and($sounds['improved'])->toBe('start:m')
        ->and(collect($sounds['items'])->pluck('key')->all())->toBe(['start:m', 'start:sz', 'start:cs']); // most answers first
});

it('does not name a best with nothing to compare it to, nor a weakest the child has firmly', function () {
    $item = soundItem('kezdo', ['sound' => 'm', 'word' => 'majom', 'emoji' => '🐒']);
    soundAnswers($this->child, $item, 'yyyyyyyy');

    expect(soundsOf($this->child))->toMatchArray(['strongest' => null, 'weakest' => null, 'improved' => null]);

    soundAnswers($this->child, soundItem('kezdo', ['sound' => 'h', 'word' => 'hal', 'emoji' => '🐟']), 'yyyyyyyn'); // 0.88 vs 1.0, both firm

    expect(soundsOf($this->child))->toMatchArray(['strongest' => 'start:m', 'weakest' => null]);

    // A lone sound the child struggles with is worth a word; so are several that are all still hard
    $lone = Child::create(['user_id' => $this->user->id, 'name' => 'Bence']);
    soundAnswers($lone, soundItem('kezdo', ['sound' => 'p', 'word' => 'papucs', 'emoji' => '🩴']), 'nnrnn');
    expect(soundsOf($lone))->toMatchArray(['strongest' => null, 'weakest' => 'start:p']);

    soundAnswers($lone, soundItem('kezdo', ['sound' => 'r', 'word' => 'róka', 'emoji' => '🦊']), 'nnrny');
    expect(soundsOf($lone))->toMatchArray(['strongest' => null, 'weakest' => 'start:p']); // r is 0.2: best of two hard ones is not "strong"
});

it('knows when a letter takes az', function (string $letters, string $article) {
    expect(Hungarian::letterArticle($letters))->toBe($article);
})->with([
    ['s', 'az'], ['sz', 'az'], ['m', 'az'], ['f', 'az'], ['l', 'az'], ['n', 'az'], ['ny', 'az'], ['r', 'az'],
    ['k', 'a'], ['zs', 'a'], ['cs', 'a'], ['gy', 'a'], ['t', 'a'], ['h', 'a'], ['b', 'a'], ['z', 'a'],
    ['ó', 'az'], ['o', 'az'], ['-ó', 'az'], ['-ál', 'az'], ['s – sz', 'az'], ['k – g', 'a'],
]);

it('writes the sounds in plain, encouraging Hungarian without putting a suffix on a letter', function () {
    $row = fn (string $kind, string $label, ?float $rate, ?float $before = null, array $more = []) => $more + [
        'key' => "$kind:$label", 'kind' => $kind, 'label' => $label, 'games' => ['kezdo'], 'examples' => [],
        'attempts' => 10, 'firstTryRate' => $rate, 'previousRate' => $before,
    ];
    $s = $row('start', 's', 0.75, 0.5);
    $k = $row('start', 'k', 0.9);
    $c = $row('contrast', 's – sz', 0.4, null, ['games' => ['ikerhangok'], 'examples' => ['só – szó', 'sál – szál', 'kéz – kés']]);
    $r = $row('rhyme', '-ó', 0.3, null, ['games' => ['rimelo'], 'examples' => ['ló', 'hó', 'tó']]);
    $say = fn (array $items, ?array $best, ?array $weak, ?array $up) => app(ReportNarrative::class)->sounds([
        'items' => $items, 'strongest' => $best['key'] ?? null, 'weakest' => $weak['key'] ?? null, 'improved' => $up['key'] ?? null,
    ]);

    // improved + best + to practise
    expect($say([$s, $k, $c], $k, $c, $s))->toBe([
        'summary' => 'Ebben az időszakban az „s” kezdőhangnál fejlődött a legtöbbet a gyermek: az elsőre jó válaszok aránya 50%-ról 75%-ra nőtt. '
            .'Legbiztosabban a „k” kezdőhangnál megy: az elsőre jó válaszok aránya 90%. '
            .'Még érdemes egy kicsit gyakorolni az „s – sz” hangpárt (elsőre jó: 40%).',
        'tip' => 'Mondjátok ki egymásnak lassan ezeket a szópárokat, és találja ki a másik, melyiket hallotta: só – szó; sál – szál.',
    ]);

    // the best one is not said twice when it is also the one that improved
    expect($say([$s, $c], $s, $c, $s)['summary'])->toBe(
        'Ebben az időszakban az „s” kezdőhangnál fejlődött a legtöbbet a gyermek: az elsőre jó válaszok aránya 50%-ról 75%-ra nőtt. '
        .'Még érdemes egy kicsit gyakorolni az „s – sz” hangpárt (elsőre jó: 40%).');

    // the article follows how the letter is read: az s, a k, az -ó
    expect($say([$r], null, $r, null)['summary'])->toBe('Még érdemes egy kicsit gyakorolni az „-ó” végű rímeket (elsőre jó: 30%).')
        ->and($say([$k, $r], $k, $r, null)['summary'])->toContain('a „k” kezdőhangnál');
});

it('gives one short home tip for the sound to practise, by kind of game', function () {
    $tip = fn (array $weak) => app(ReportNarrative::class)->sounds([
        'items' => [$weak], 'strongest' => null, 'weakest' => $weak['key'], 'improved' => null,
    ])['tip'];
    $base = ['key' => 'x', 'attempts' => 6, 'firstTryRate' => 0.3, 'previousRate' => null, 'examples' => []];

    expect($tip(['kind' => 'start', 'label' => 'sz', 'games' => ['kezdo'], 'examples' => ['szív', 'szék', 'szem']] + $base))
        ->toBe('Keressetek otthon három dolgot, aminek a neve ezzel a hanggal kezdődik: sz (például: szív, szék).')
        ->and($tip(['kind' => 'start', 'label' => 'zs', 'games' => ['kezdo']] + $base))
        ->toBe('Keressetek otthon három dolgot, aminek a neve ezzel a hanggal kezdődik: zs.')
        ->and($tip(['kind' => 'contrast', 'label' => 's – zs', 'games' => ['zs', 'ikerhangok']] + $base))
        ->toBe('Keressetek otthon olyan szavakat, amelyekben zümmögő zs hallatszik (például: zsiráf, rúzs), és olyanokat, amelyekben csendes s (például: sajt, hús).')
        ->and($tip(['kind' => 'contrast', 'label' => 'l – ll', 'games' => ['ikerhangok']] + $base))
        ->toBe('Találjatok ki otthon szópárokat, amelyek csak egy hangban különböznek (l – ll), mondjátok ki őket lassan, és találja ki a másik, melyiket hallotta.')
        ->and($tip(['kind' => 'rhyme', 'label' => '-ó', 'games' => ['rimelo'], 'examples' => ['ló', 'hó', 'tó']] + $base))
        ->toBe('Rímjáték otthon: mondjatok egy szót (például: ló), és keressetek hozzá együtt három rímelő szót (például: hó, tó).');
});

it('says nothing when there is nothing to say', function () {
    $sounds = soundsOf($this->child);

    expect($sounds)->toMatchArray(['items' => [], 'attempts' => 0, 'few' => 0])
        ->and(app(ReportNarrative::class)->sounds($sounds))->toBe(['summary' => null, 'tip' => null]);
});

it('adds the sounds, with the story and a tip, to the parent\'s progress report', function () {
    $s = soundItem('kezdo', ['sound' => 's', 'word' => 'sajt', 'emoji' => '🧀']);
    $m = soundItem('kezdo', ['sound' => 'm', 'word' => 'majom', 'emoji' => '🐒']);
    soundAnswers($this->child, $s, 'yyyyyyyy');
    soundAnswers($this->child, $m, 'nnnrn');

    $sounds = actingAs($this->user)->getJson("/api/beszed/children/{$this->child->id}/progress?days=30")->assertOk()->json('sounds');

    expect($sounds['items'])->toHaveCount(2)
        ->and($sounds['items'][0])->toHaveKeys(['key', 'kind', 'label', 'games', 'examples', 'attempts', 'firstTryRate', 'previousRate', 'trend', 'band', 'overallAttempts', 'overallRate'])
        ->and($sounds)->toMatchArray(['strongest' => 'start:s', 'weakest' => 'start:m', 'attempts' => 13, 'few' => 0])
        ->and($sounds['summary'])->toBe('Legbiztosabban az „s” kezdőhangnál megy: az elsőre jó válaszok aránya 100%. Még érdemes egy kicsit gyakorolni az „m” kezdőhangot (elsőre jó: 0%).')
        ->and($sounds['tip'])->toBe('Keressetek otthon három dolgot, aminek a neve ezzel a hanggal kezdődik: m (például: majom).');

    actingAs(User::factory()->create())->getJson("/api/beszed/children/{$this->child->id}/progress")->assertForbidden();
});

it('shows the therapist the same sounds, without the home tip and nothing else about the child', function () {
    soundAnswers($this->child, soundItem('kezdo', ['sound' => 's', 'word' => 'sajt', 'emoji' => '🧀']), 'yyyyyyyy', 60);
    soundAnswers($this->child, soundItem('rimelo', ['word' => 'ló', 'emoji' => '🐴', 'rhyme' => 'ó']), 'nnrnn', 10);

    $url = actingAs($this->user)->postJson("/api/beszed/children/{$this->child->id}/shares", ['days' => 30])->assertCreated()->json('url');
    auth()->forgetGuards();
    $report = getJson('/api/share/'.substr($url, strrpos($url, '/') + 1))->assertOk()->json();

    expect($report['sounds'])->toHaveKeys(['minAttempts', 'attempts', 'few', 'strongest', 'weakest', 'improved', 'items', 'summary'])
        ->not->toHaveKey('tip')
        ->and($report['sounds']['items'])->toHaveCount(2)   // the share looks 90 days back
        ->and($report['sounds']['weakest'])->toBe('rhyme:-ó')
        ->and($report['sounds']['summary'])->toContain('az „-ó” végű rímeket')
        ->and(json_encode($report))->not->toContain($this->user->email)->not->toContain('birth');
});

it('puts the sounds, the story and the tip in the Sunday e-mail and its PDF page', function () {
    $s = soundItem('kezdo', ['sound' => 's', 'word' => 'sajt', 'emoji' => '🧀']);
    $m = soundItem('kezdo', ['sound' => 'm', 'word' => 'majom', 'emoji' => '🐒']);
    soundAnswers($this->child, $s, 'yyyyyyyy', 2);
    soundAnswers($this->child, $m, 'nnnrn', 2);
    BeszedSession::create(['child_id' => $this->child->id, 'game' => 'kezdo', 'level' => 1, 'rounds' => 13, 'correct' => 9, 'first_try' => 8, 'duration_ms' => 120_000]);

    $report = app(WeeklyReport::class)->for($this->child);

    expect($report['sounds']['items'])->toHaveCount(2)
        ->and($report['narrative'])->toContain('Még érdemes egy kicsit gyakorolni az „m” kezdőhangot')
        ->and($report['tips'][0])->toBe('Keressetek otthon három dolgot, aminek a neve ezzel a hanggal kezdődik: m (például: majom).')
        ->and(count($report['tips']))->toBeGreaterThan(1);

    $email = view('emails.weekly-report', ['r' => $report, 'unsubscribeUrl' => 'https://example.test/x'])->render();
    $pdf = view('pdf.weekly-report', ['r' => $report])->render();

    foreach ([$email, $pdf] as $html) {
        expect($html)->toContain('Hangok')->toContain('„m”')->toContain('kezdőhang')->toContain('elsőre jó: 0%')
            ->toContain('Keressetek otthon három dolgot');
    }
});

it('leaves the sounds block out of the e-mail when there is nothing to show', function () {
    BeszedSession::create(['child_id' => $this->child->id, 'game' => 'zs', 'level' => 1, 'rounds' => 3, 'correct' => 3, 'first_try' => 3, 'duration_ms' => 60_000]);

    $report = app(WeeklyReport::class)->for($this->child);

    expect($report['sounds']['items'])->toBe([]);
    expect(view('emails.weekly-report', ['r' => $report, 'unsubscribeUrl' => 'x'])->render())->not->toContain('>Hangok<');
});

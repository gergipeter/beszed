<?php

use App\Beszed\Content\ContentRules;
use App\Beszed\Rounds\HanggyakorloRounds;
use App\Models\BeszedContentItem;
use App\Models\BeszedSkillLevel;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

/** @return list<array{0: array, 1: array}> [round, its content payload] */
function hanggyakorloRounds(int $level, ?string $category = null): array
{
    BeszedSkillLevel::updateOrCreate(['child_id' => test()->child->id, 'game' => 'hanggyakorlo'], ['level' => $level]);
    $query = $category ? "&category=$category" : '';
    $rounds = actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=hanggyakorlo$query")
        ->assertOk()->json('rounds');

    return array_map(fn ($r) => [$r, BeszedContentItem::find($r['content_item_id'])->payload], $rounds);
}

it('practises only the sound the child picked', function (string $sound) {
    foreach ([1, 2, 3] as $level) {
        foreach (hanggyakorloRounds($level, $sound) as [$round, $p]) {
            expect($round['engine'])->toBe('say')
                ->and($p['sound'])->toBe($sound)
                ->and($round['data']['sound'])->toBe(config("beszed.games.hanggyakorlo.categories.$sound.name"));
        }
    }
})->with(HanggyakorloRounds::SOUNDS);

it('mixes the sounds without a pick, or with "vegyesen"', function (?string $category) {
    $sounds = collect(hanggyakorloRounds(1, $category))->map(fn ($r) => $r[1]['sound']);

    expect($sounds)->toHaveCount(config('beszed.games.hanggyakorlo.rounds'))
        ->and($sounds->unique()->count())->toBeGreaterThanOrEqual(6);
})->with([null, 'vegyes']);

it('levels pick the right positions and modes', function () {
    foreach (hanggyakorloRounds(1, 'r') as [$round, $p]) {
        expect($p['pos'])->toBe('start')->and($round['data']['mode'])->toBe('repeat')
            ->and($round['prompt']['text'])->toContain($p['word']);
    }
    foreach (hanggyakorloRounds(2, 'sz') as [$round, $p]) {
        expect($p['pos'])->toBeIn(['middle', 'end'])->and($round['data']['mode'])->toBe('repeat');
    }

    $level3 = hanggyakorloRounds(3, 'k');
    $phrases = collect($level3)->filter(fn ($r) => $r[1]['pos'] === 'phrase');
    $named = collect($level3)->filter(fn ($r) => $r[0]['data']['mode'] === 'name');
    expect($phrases->count())->toBeGreaterThanOrEqual(2)->and($named->count())->toBeGreaterThanOrEqual(4);
    foreach ($named as [$round, $p]) {
        // naming: Csillám doesn't give the word away before the child has said it
        expect($p['naming'])->toBe('yes')->and($p['pos'])->not->toBe('phrase')
            ->and($round['data']['model'])->toBeNull()
            ->and(mb_strtolower($round['prompt']['text']))->not->toContain($p['word'])
            ->and($round['data']['onCorrect'])->toContain($p['word']);
    }
    foreach ($phrases as [$round, $p]) {
        expect($round['data']['mode'])->toBe('repeat')->and($round['prompt']['text'])->toContain($p['word']);
    }
});

it('sends consistent answer data', function () {
    foreach ([1, 2, 3] as $level) {
        foreach (hanggyakorloRounds($level) as [$round, $p]) {
            $d = $round['data'];
            $phrase = $p['pos'] === 'phrase';
            expect($d['word'])->toBe($p['word'])
                ->and($d['emoji'])->not->toBeEmpty()
                ->and($d['onCorrect'])->not->toBeEmpty()
                ->and($d['onSkip'])->not->toBeEmpty()
                ->and($d['skipAfter'])->toBe(3)
                // the slow pieces add up to the word (syllables) or the sentence (its words)
                ->and(end($d['slow']))->toBe($phrase ? $p['word'] : mb_strtoupper(mb_substr($p['word'], 0, 1)).mb_substr($p['word'], 1).'.')
                ->and(mb_strtolower(implode($phrase ? ' ' : '', count($d['slow']) > 1 ? array_slice($d['slow'], 0, -1) : [$p['word']])))
                ->toBe(mb_strtolower($phrase ? $p['word'] : implode('', HanggyakorloRounds::syllables($p['word']))))
                // Csillám can say every piece: a vowel in each (a lone consonant is spelled out), no "…" (read aloud as "pont pont pont")
                ->and(collect([...$d['slow'], ...$d['retry'], $round['prompt']['text'], $d['onCorrect']])->every(fn ($s) => preg_match('/[aáeéiíoóöőuúüű]/iu', $s) && ! str_contains($s, '…')))->toBeTrue()
                ->and(implode(' ', $d['retry']))->toContain($phrase ? $p['word'] : rtrim(end($d['slow']), '.'));
            if ($d['mode'] === 'repeat') {
                expect($d['model'])->toBe(end($d['slow']));
            }
        }
    }
});

it('has deep, correct content for every sound', function () {
    $rows = collect(json_decode(file_get_contents(database_path('seeders/data/beszed/hanggyakorlo.json')), true));
    expect($rows->count())->toBeGreaterThanOrEqual(180);

    foreach (HanggyakorloRounds::SOUNDS as $sound) {
        $mine = $rows->filter(fn ($r) => $r['payload']['sound'] === $sound);
        $pos = $mine->countBy(fn ($r) => $r['payload']['pos']);
        // Hungarian has hardly any words starting with "ty" besides the tyúk family
        expect($mine->count())->toBeGreaterThanOrEqual(15, $sound)
            ->and($pos['start'] ?? 0)->toBeGreaterThanOrEqual($sound === 'ty' ? 5 : 6, $sound)
            ->and(($pos['middle'] ?? 0) + ($pos['end'] ?? 0))->toBeGreaterThanOrEqual(6, $sound)
            ->and($pos['phrase'] ?? 0)->toBeGreaterThanOrEqual(3, $sound)
            // enough pictures to name on the 3rd level
            ->and($mine->where('payload.naming', 'yes')->count())->toBeGreaterThanOrEqual(4, $sound);
    }
    // every single word can be said slowly, syllable by syllable
    foreach ($rows->where('payload.pos', '!=', 'phrase') as $r) {
        expect(HanggyakorloRounds::syllables($r['payload']['word']))->not->toBeNull($r['payload']['word']);
    }
});

it('rejects words that do not train the sound where they say', function (array $payload, string $field) {
    expect(ContentRules::check('hanggyakorlo', $payload + ['emoji' => '🦊']))->toHaveKey($field);
})->with([
    'sz is not s' => [['word' => 'szék', 'sound' => 's', 'pos' => 'start'], 'pos'],
    'cs is not c' => [['word' => 'csiga', 'sound' => 'c', 'pos' => 'start'], 'pos'],
    'zs is not z' => [['word' => 'zsiráf', 'sound' => 'z', 'pos' => 'start'], 'pos'],
    'gy is not g' => [['word' => 'gyík', 'sound' => 'g', 'pos' => 'start'], 'pos'],
    'not at the end' => [['word' => 'róka', 'sound' => 'r', 'pos' => 'end'], 'pos'],
    'at the start, not the middle' => [['word' => 'kakas', 'sound' => 'k', 'pos' => 'middle'], 'pos'],
    's word that also has sz' => [['word' => 'szarvas', 'sound' => 's', 'pos' => 'end'], 'word'],
    'r word that also has l' => [['word' => 'repülő', 'sound' => 'r', 'pos' => 'start'], 'word'],
    'phrase with the sound twice' => [['word' => 'Rozi ráz.', 'sound' => 'r', 'pos' => 'phrase'], 'word'],
    'a phrase as a word' => [['word' => 'A róka rág.', 'sound' => 'r', 'pos' => 'start'], 'word'],
]);

it('accepts long sounds and sounds inside words', function () {
    expect(ContentRules::check('hanggyakorlo', ['word' => 'hattyú', 'emoji' => '🦢', 'sound' => 'ty', 'pos' => 'middle']))->toBe([])
        ->and(ContentRules::check('hanggyakorlo', ['word' => 'busz', 'emoji' => '🚌', 'sound' => 'sz', 'pos' => 'end']))->toBe([])
        ->and(HanggyakorloRounds::syllables('hattyú'))->toBe(['haty', 'tyú'])
        ->and(HanggyakorloRounds::syllables('asszony'))->toBe(['asz', 'szony'])
        ->and(ContentRules::check('hanggyakorlo', ['word' => 'Rozi rózsát ráz.', 'emoji' => '👧🌹', 'sound' => 'r', 'pos' => 'phrase']))->toBe([]);
});

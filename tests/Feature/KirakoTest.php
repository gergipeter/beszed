<?php

use App\Beszed\Rounds\KirakoRounds;
use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use App\Models\BeszedSkillLevel;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

// Kirakó: a session is three puzzles, each one pálya harder; no picture twice; the level moves on after a tidy solve.

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create(); // premium
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    $this->free = User::factory()->free()->create();
    $this->freeChild = Child::create(['user_id' => $this->free->id, 'name' => 'Bence']);
});

function kirakoSession(User $user, Child $child, string $query = ''): array
{
    return actingAs($user)->getJson("/api/beszed/children/{$child->id}/session?game=kirako$query")->assertOk()->json();
}

function kirakoLevels(array $session): array
{
    return array_map(fn ($r) => (int) $r['data']['levelLabel'], $session['rounds']);
}

function kirakoPictures(array $session): array
{
    return array_map(fn ($r) => $r['data']['emoji'], $session['rounds']);
}

function kirakoAnswer(User $user, Child $child, int $level, int $tries, bool $correct = true): array
{
    return actingAs($user)->postJson("/api/beszed/children/{$child->id}/attempts",
        ['game' => 'kirako', 'level' => $level, 'correct' => $correct, 'tries' => $tries])->assertCreated()->json();
}

it('makes each puzzle of a session one pálya harder than the one before', function () {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'kirako', 'level' => 6, 'streak' => 0]);

    $session = kirakoSession($this->user, $this->child);

    expect(kirakoLevels($session))->toBe([6, 7, 8]);
    // level 6 is a 2×2 board, level 7 the first 3×2 one
    expect([$session['rounds'][0]['data']['cols'], $session['rounds'][0]['data']['rows']])->toBe([2, 2])
        ->and([$session['rounds'][1]['data']['cols'], $session['rounds'][1]['data']['rows']])->toBe([3, 2]);
});

it('never shows the same picture twice in a session', function () {
    foreach (range(1, 40) as $i) {
        $pictures = kirakoPictures(kirakoSession($this->user, $this->child));

        expect(array_unique($pictures))->toHaveCount(count($pictures));
    }
});

it('does not bring a picture back until many others have been played', function () {
    // the 120 pictures played most recently
    $played = BeszedContentItem::forGame('kirako')->limit(1000)->get()->unique(fn ($i) => $i->payload['emoji'])->take(120);
    foreach ($played as $item) {
        BeszedAttempt::create(['child_id' => $this->child->id, 'game' => 'kirako', 'content_item_id' => $item->id, 'level' => 1, 'correct' => true, 'tries' => 1]);
    }
    $recent = $played->map(fn ($i) => $i->payload['emoji'])->all();

    $again = 0;
    foreach (range(1, 20) as $i) {
        $again += count(array_intersect(kirakoPictures(kirakoSession($this->user, $this->child)), $recent));
    }

    // 60 pictures shown; chance alone would repeat about a sixth of them
    expect($again)->toBeLessThanOrEqual(3);
});

it('does not repeat a picture just because the puzzle took a few swaps', function () {
    $item = BeszedContentItem::forGame('kirako')->get()->first(fn ($i) => ($i->payload['kind'] ?? null) !== 'tale');
    foreach (range(1, 6) as $i) { // solved, but with plenty of wasted swaps (graded 3)
        BeszedAttempt::create(['child_id' => $this->child->id, 'game' => 'kirako', 'content_item_id' => $item->id, 'level' => 3, 'correct' => true, 'tries' => 3]);
    }

    $shown = 0;
    foreach (range(1, 20) as $i) {
        $shown += count(array_filter(kirakoPictures(kirakoSession($this->user, $this->child)), fn ($p) => $p === $item->payload['emoji']));
    }

    expect($shown)->toBeLessThanOrEqual(3); // without the fix it was nearly every session
});

it('moves on to the next pálya after a tidy solve (grade 1 or 2), and steps back after a messy one', function () {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'kirako', 'level' => 20, 'streak' => 0]);

    expect(kirakoAnswer($this->user, $this->child, 20, 1)['level'])->toBe(21)
        ->and(kirakoAnswer($this->user, $this->child, 21, 2)['level'])->toBe(22)
        ->and(kirakoAnswer($this->user, $this->child, 22, 3)['level'])->toBe(21);
});

it('lets a free account play the first 15 pálya, keeps the level it reached, and never lowers it', function () {
    BeszedSkillLevel::create(['child_id' => $this->freeChild->id, 'game' => 'kirako', 'level' => 25, 'streak' => 0]);

    $session = kirakoSession($this->free, $this->freeChild, '&level=40');

    // the pick above the free levels plays the last free one…
    expect($session['level'])->toBe(15)->and($session['level_cap'])->toBe(15)
        ->and(max(kirakoLevels($session)))->toBeLessThanOrEqual(15)
        // …and the level the child has reached is still there for when they upgrade
        ->and(BeszedSkillLevel::where('child_id', $this->freeChild->id)->value('level'))->toBe(25);

    // a pick inside the free levels is kept
    expect(kirakoSession($this->free, $this->freeChild, '&level=9')['level'])->toBe(9)
        ->and(BeszedSkillLevel::where('child_id', $this->freeChild->id)->value('level'))->toBe(9);
});

it('keeps the three puzzles of a free account inside its levels', function () {
    BeszedSkillLevel::create(['child_id' => $this->freeChild->id, 'game' => 'kirako', 'level' => 14, 'streak' => 0]);

    expect(kirakoLevels(kirakoSession($this->free, $this->freeChild)))->toBe([14, 15, 15]);
});

it('goes to the top for a premium account', function () {
    BeszedSkillLevel::create(['child_id' => $this->child->id, 'game' => 'kirako', 'level' => 199, 'streak' => 0]);

    expect(kirakoLevels(kirakoSession($this->user, $this->child)))->toBe([199, 200, 200]);
    expect(KirakoRounds::LEVELS)->toBe(200);
});

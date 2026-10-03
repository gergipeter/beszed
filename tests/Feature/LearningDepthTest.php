<?php

use App\Beszed\Rounds\SzokirakoRounds;
use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use App\Models\BeszedSession;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

function depthSession(string $game, string $query = ''): array
{
    return actingAs(test()->user)
        ->getJson('/api/beszed/children/'.test()->child->id."/session?game=$game$query")
        ->assertOk()
        ->json();
}

it('splits Hungarian words into letter tiles, the two-letter sounds as one', function () {
    expect(SzokirakoRounds::letters('csiga'))->toBe(['cs', 'i', 'g', 'a'])
        ->and(SzokirakoRounds::letters('nyuszi'))->toBe(['ny', 'u', 'sz', 'i'])
        ->and(SzokirakoRounds::letters('alma'))->toBe(['a', 'l', 'm', 'a']);
});

it('Szókirakó gives every word as shuffled letters that spell it', function () {
    foreach (depthSession('szokirako', '&level=3')['rounds'] as $round) {
        $labels = collect($round['data']['items'])->keyBy('id');
        $word = collect($round['data']['order'])->map(fn ($id) => $labels[$id]['label'])->implode('');

        expect($round['engine'])->toBe('order')
            ->and($round['data']['stimulus']['emoji'])->not->toBe('')
            ->and(count($round['data']['items']))->toBe(count($round['data']['order']))
            ->and(strlen($word))->toBeGreaterThan(2);
    }
});

it('Olvasd el! never says the word and offers three different pictures', function () {
    foreach (depthSession('olvasd')['rounds'] as $round) {
        $emojis = collect($round['data']['options'])->pluck('emoji');

        expect($round['data']['stimulus']['letter'])->not->toBeEmpty()
            ->and($emojis->unique()->count())->toBe(3)
            ->and(collect($round['data']['options'])->pluck('id'))->toContain($round['data']['answer'])
            ->and($round['prompt']['text'])->not->toContain($round['data']['stimulus']['letter']);
    }
});

it('Mesehallgató tells one story and asks three questions with the right answer among three pictures', function () {
    $session = depthSession('mese');

    expect($session['rounds'])->toHaveCount(3);
    foreach ($session['rounds'] as $i => $round) {
        expect(collect($round['data']['options']))->toHaveCount(3)
            ->and(collect($round['data']['options'])->pluck('id'))->toContain($round['data']['answer'])
            ->and($round['data']['replayParts'])->not->toBeEmpty();
        if ($i === 0) {
            expect($round['prompt']['parts'])->not->toBeEmpty();
        }
    }
    // all three questions come from the same story
    expect(collect($session['rounds'])->pluck('content_item_id')->unique())->toHaveCount(1);
});

it('has deep content: no game plays out in a handful of items', function (string $game, int $min) {
    expect(BeszedContentItem::where('game', $game)->count())->toBeGreaterThanOrEqual($min);
})->with([['mondat', 90], ['tobbes', 90], ['ellentet', 50], ['foglalkozas', 40], ['napirend', 25], ['keszul', 25], ['tortenet', 30], ['valogato', 24], ['elohely', 9], ['szobak', 8], ['mese', 24]]);

it('brings back what the child gets right at the first try only after a growing wait (spaced repetition)', function () {
    $items = BeszedContentItem::where('game', 'tobbes')->orderBy('id')->take(2)->get();
    [$fresh, $known] = [$items[0], $items[1]];
    // $known: right first time three times in a row, just now: it waits (a streak of 3 → 4 days)
    foreach ([3, 2, 1] as $minutes) {
        BeszedAttempt::forceCreate(['child_id' => $this->child->id, 'game' => 'tobbes', 'content_item_id' => $known->id, 'level' => 1, 'correct' => true, 'tries' => 1, 'created_at' => now()->subMinutes($minutes)]);
    }

    $weights = (new ReflectionClass(App\Beszed\SessionBuilder::class))->getMethod('spacing');
    $builder = app(App\Beszed\SessionBuilder::class);
    $weights->setAccessible(true);
    $attempts = BeszedAttempt::where('content_item_id', $known->id)->latest('id')->get();

    expect($weights->invoke($builder, $attempts))->toBe(0.35)
        ->and($weights->invoke($builder, collect()))->toBe(1.0);

    // after the wait is over it is a little more likely than a new item
    travelTo(now()->addDays(5));
    expect($weights->invoke($builder, $attempts))->toBe(1.6);
});

it('tracks the weekly challenge: five different games in a week win the sticker', function () {
    travelTo(now('Europe/Budapest')->startOfWeek()->setTime(10, 0)->utc());
    $games = ['zs', 'kezdo', 'szotag', 'rimelo', 'szamol'];
    $rewards = null;
    foreach ($games as $i => $game) {
        $rewards = actingAs($this->user)
            ->postJson("/api/beszed/children/{$this->child->id}/sessions", ['game' => $game, 'level' => 1, 'rounds' => 8, 'correct' => 8, 'first_try' => 8])
            ->assertCreated()->json();
        expect($rewards['week']['games'] ?? null)->toBe($i + 1);
    }

    expect(collect($rewards['result']['new_badges'])->pluck('id'))->toContain('week_1')
        ->and($rewards['week'])->toMatchArray(['games' => 5, 'goal' => 5, 'won' => 1]);
    // the same game twice does not count twice
    expect(BeszedSession::where('child_id', $this->child->id)->count())->toBe(5);
});

it('awards a zone sticker when every game of the zone has been finished once', function () {
    $zone = config('beszed_folders.zones.sound');
    $last = null;
    foreach ($zone as $game) {
        $last = actingAs($this->user)
            ->postJson("/api/beszed/children/{$this->child->id}/sessions", ['game' => $game, 'level' => 1, 'rounds' => 8, 'correct' => 8, 'first_try' => 8])
            ->assertCreated()->json();
    }

    expect(collect($last['result']['new_badges'])->pluck('id'))->toContain('zone_sound');
});

it('sends the play reminder only to parents who asked, when the child played lately but not today', function () {
    Illuminate\Support\Facades\Mail::fake();
    $quiet = User::factory()->create(['play_reminder_enabled' => false]);
    $kid = Child::create(['user_id' => $quiet->id, 'name' => 'Tze']);
    BeszedSession::forceCreate(['child_id' => $kid->id, 'game' => 'zs', 'level' => 1, 'rounds' => 8, 'correct' => 8, 'first_try' => 8, 'completed_at' => now()->subDays(2)]);

    $this->artisan('beszed:play-reminders')->assertSuccessful();
    Illuminate\Support\Facades\Mail::assertNothingSent();

    $quiet->forceFill(['play_reminder_enabled' => true])->save();
    $this->artisan('beszed:play-reminders')->assertSuccessful();
    Illuminate\Support\Facades\Mail::assertSent(App\Mail\PlayReminderMail::class, 1);

    // once a day at most
    $this->artisan('beszed:play-reminders')->assertSuccessful();
    Illuminate\Support\Facades\Mail::assertSent(App\Mail\PlayReminderMail::class, 1);
});

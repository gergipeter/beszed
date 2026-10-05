<?php

use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use App\Models\Child;
use App\Models\User;
use Database\Seeders\BeszedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(BeszedContentSeeder::class);
    config(['beszed_content.admins' => ['editor@example.test']]);
    $this->editor = User::factory()->create(['email' => 'Editor@Example.test']);
    $this->parent = User::factory()->create();
});

it('opens the editor only to the listed e-mails', function () {
    getJson('/api/admin/content')->assertUnauthorized();
    actingAs($this->parent)->getJson('/api/admin/content')->assertForbidden();
    actingAs($this->parent)->getJson('/api/me')->assertJsonPath('user.can_edit_content', false);

    actingAs($this->editor)->getJson('/api/me')->assertJsonPath('user.can_edit_content', true);
    $games = actingAs($this->editor)->getJson('/api/admin/content')->assertOk()->json('games');
    expect(collect($games)->pluck('id')->all())->toBe(array_keys(config('beszed.games')))
        ->and($games[0]['schema']['fields'])->not->toBeEmpty()
        ->and(collect($games)->firstWhere('id', 'zs')['active'])->toBe(BeszedContentItem::forGame('zs')->count());
});

it('adds an item after checking it, and only keeps schema fields', function () {
    actingAs($this->editor)->postJson('/api/admin/content/szotag', [
        'level' => 2, 'payload' => ['word' => 'lepke', 'emoji' => '🦋', 'syllables' => ['lep', 'ke', 'x']],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.syllables');

    $item = actingAs($this->editor)->postJson('/api/admin/content/szotag', [
        'level' => 2, 'payload' => ['word' => ' lepke ', 'emoji' => '🦋', 'syllables' => ['lep', 'ke'], 'evil' => '<script>'],
    ])->assertCreated()->json('item');

    actingAs($this->editor)->postJson('/api/admin/content/szotag', [
        'level' => 2, 'payload' => ['word' => 'Lepke', 'emoji' => '🦋', 'syllables' => ['Lep', 'ke']],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.word');

    expect($item['payload'])->toBe(['word' => 'lepke', 'emoji' => '🦋', 'syllables' => ['lep', 'ke']])
        ->and($item['source'])->toBe('admin')
        ->and(BeszedContentItem::forGame('szotag')->where('id', $item['id'])->exists())->toBeTrue();
});

it('edits a seed item, and the seeder leaves it alone afterwards', function () {
    $item = BeszedContentItem::where('game', 'zs')->where('payload->word', 'zsiráf')->sole();

    actingAs($this->editor)->putJson("/api/admin/content/zs/{$item->id}", [
        'level' => 3, 'payload' => ['word' => 'zsiráf', 'emoji' => '🦒', 'sound' => 'zs'],
    ])->assertOk()->assertJsonPath('item.edited', true);

    seed(BeszedContentSeeder::class);
    expect($item->fresh()->level)->toBe(3);

    // an item of another game is not reachable under this one
    $other = BeszedContentItem::where('game', 'papagaj')->first();
    actingAs($this->editor)->putJson("/api/admin/content/zs/{$other->id}", ['level' => 1, 'payload' => []])->assertNotFound();
});

it('deletes unused items but only switches off played ones', function () {
    $own = BeszedContentItem::create(['game' => 'papagaj', 'level' => 1, 'payload' => ['word' => 'dió', 'emoji' => '🌰'], 'source' => 'admin', 'edited_at' => now()]);
    actingAs($this->editor)->deleteJson("/api/admin/content/papagaj/{$own->id}")->assertJsonPath('deleted', true);
    expect(BeszedContentItem::find($own->id))->toBeNull();

    $played = BeszedContentItem::where('game', 'zs')->first();
    $child = Child::create(['user_id' => $this->parent->id, 'name' => 'Zoé']);
    BeszedAttempt::create(['child_id' => $child->id, 'game' => 'zs', 'content_item_id' => $played->id, 'level' => 1, 'correct' => true, 'tries' => 1]);

    actingAs($this->editor)->deleteJson("/api/admin/content/zs/{$played->id}")->assertJsonPath('deleted', false);
    expect($played->fresh()->active)->toBeFalse()
        ->and(BeszedAttempt::sole()->content_item_id)->toBe($played->id);
});

it('shows who changed an item and what, newest first', function () {
    $item = actingAs($this->editor)->postJson('/api/admin/content/papagaj', [
        'level' => 1, 'payload' => ['word' => 'kockacukor', 'emoji' => '🧊'],
    ])->assertCreated()->json('item');

    $this->travel(1)->minutes();
    actingAs($this->editor)->putJson("/api/admin/content/papagaj/{$item['id']}", [
        'level' => 2, 'payload' => ['word' => 'pemzli', 'emoji' => '🧊'],
    ])->assertOk();

    $history = actingAs($this->editor)->getJson("/api/admin/content/papagaj/{$item['id']}/history")
        ->assertOk()->json('history');

    expect($history)->toHaveCount(2)
        ->and($history[0]['action'])->toBe('updated')
        ->and($history[0]['editor_email'])->toBe('editor@example.test')
        ->and($history[0]['changes'])->toBe([
            ['field' => 'level', 'before' => 1, 'after' => 2],
            ['field' => 'payload.word', 'before' => 'kockacukor', 'after' => 'pemzli'],
        ])
        ->and($history[1]['action'])->toBe('created');
});

it('keeps the history to editors and to the item\'s own game', function () {
    $item = BeszedContentItem::where('game', 'zs')->first();

    actingAs($this->parent)->getJson("/api/admin/content/zs/{$item->id}/history")->assertForbidden();
    actingAs($this->editor)->getJson("/api/admin/content/papagaj/{$item->id}/history")->assertNotFound();
    actingAs($this->editor)->getJson("/api/admin/content/zs/{$item->id}/history")
        ->assertOk()->assertExactJson(['history' => []]);
});

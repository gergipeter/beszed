<?php

use App\Models\BeszedAttempt;
use App\Models\BeszedRecording;
use App\Models\BeszedSession;
use App\Models\Child;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Anna']);
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
});

it('asks for consent until the parent accepts the current notice version', function () {
    actingAs($this->user)->getJson('/api/me')->assertJsonPath('consent.required', true);

    actingAs($this->user)->postJson('/api/me/consent', [])->assertStatus(422);
    actingAs($this->user)->postJson('/api/me/consent', ['accept' => true])->assertOk()->assertJsonPath('consent.required', false);
    actingAs($this->user)->getJson('/api/me')->assertJsonPath('consent.required', false);
    expect($this->user->fresh()->consented_at)->not->toBeNull();

    // A new notice version asks again.
    config(['privacy.version' => '2099-01']);
    actingAs($this->user)->getJson('/api/me')->assertJsonPath('consent.required', true);
});

it('exports the parent\'s and children\'s data, and nobody else\'s', function () {
    BeszedAttempt::create(['child_id' => $this->child->id, 'game' => 'zs', 'level' => 1, 'correct' => true, 'tries' => 1]);
    $other = Child::create(['user_id' => User::factory()->create()->id, 'name' => 'Idegen']);
    BeszedAttempt::create(['child_id' => $other->id, 'game' => 'zs', 'level' => 1, 'correct' => false, 'tries' => 2]);

    $response = actingAs($this->user)->get('/api/me/export')->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment')->toContain('.json');
    $data = json_decode($response->streamedContent(), true);

    expect($data['parent']['name'])->toBe('Anna')
        ->and($data['children'])->toHaveCount(1)
        ->and($data['children'][0]['name'])->toBe('Zoé')
        ->and($data['children'][0]['answers'])->toHaveCount(1)
        ->and(json_encode($data))->not->toContain('Idegen');
});

it('deletes the account, every child\'s results and the recording files', function () {
    Storage::fake('local');
    config(['beszed.recordings.disk' => 'local']);
    actingAs($this->user)->post('/api/beszed/recordings', [
        'line_key' => 'greet',
        'file' => UploadedFile::fake()->create('greet.webm', 20, 'audio/webm'),
    ]);
    $path = BeszedRecording::sole()->path;
    Storage::disk('local')->assertExists($path);
    BeszedAttempt::create(['child_id' => $this->child->id, 'game' => 'zs', 'level' => 1, 'correct' => true, 'tries' => 1]);
    BeszedSession::create(['child_id' => $this->child->id, 'game' => 'zs', 'level' => 1, 'rounds' => 8, 'correct' => 8, 'first_try' => 8]);

    actingAs($this->user)->deleteJson('/api/me', ['confirm' => 'igen'])->assertStatus(422);
    // Like the SPA: a same-site Referer makes Sanctum use the cookie session, which gets ended.
    actingAs($this->user)->withHeader('Referer', config('app.url').'/gyerekek')
        ->deleteJson('/api/me', ['confirm' => 'TÖRLÉS'])->assertNoContent();

    expect(User::find($this->user->id))->toBeNull()
        ->and(Child::count())->toBe(0)
        ->and(BeszedAttempt::count())->toBe(0)
        ->and(BeszedSession::count())->toBe(0)
        ->and(BeszedRecording::count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
    assertGuest('web');
});

it('shares the privacy settings with the app shell', function () {
    config(['privacy.controller' => 'Példa Kft.', 'privacy.contact' => 'adat@example.hu']);
    $html = $this->withoutVite()->get('/adatvedelem')->assertOk()->getContent();

    expect($html)->toMatch('/<script id="app-config" type="application\/json">(.*?)<\/script>/s');
    preg_match('/<script id="app-config" type="application\/json">(.*?)<\/script>/s', $html, $m);
    expect(json_decode($m[1], true)['privacy'])->toMatchArray(['controller' => 'Példa Kft.', 'contact' => 'adat@example.hu']);
});

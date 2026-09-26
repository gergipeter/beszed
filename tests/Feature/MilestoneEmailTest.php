<?php

use App\Models\Child;
use App\Models\User;
use App\Notifications\MilestoneEarned;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->child = Child::create(['user_id' => $this->user->id, 'name' => 'Zoé']);
    travelTo(now('Europe/Budapest')->setTime(10, 0)->utc());
});

function finishGameFor(User $user, Child $child): void
{
    actingAs($user)->postJson("/api/beszed/children/{$child->id}/sessions",
        ['game' => 'zs', 'level' => 1, 'rounds' => 8, 'correct' => 8, 'first_try' => 8]);
}

it('mails the parent when a milestone badge is earned', function () {
    Notification::fake();
    $day = now();

    foreach ([0, 1, 2] as $offset) {
        travelTo($day->copy()->addDays($offset));
        finishGameFor($this->user, $this->child);
    }

    Notification::assertSentTo($this->user, MilestoneEarned::class, fn ($n) => $n->badgeId() === 'streak_3');
});

it('does not mail for badges that are not marked email-worthy', function () {
    Notification::fake();

    finishGameFor($this->user, $this->child); // earns first_game and perfect, neither is email-worthy

    Notification::assertNothingSent();
});

it('respects the parent turning milestone e-mails off', function () {
    Notification::fake();
    $this->user->update(['milestone_emails_enabled' => false]);
    $day = now();

    foreach ([0, 1, 2] as $offset) {
        travelTo($day->copy()->addDays($offset));
        finishGameFor($this->user, $this->child);
    }

    Notification::assertNothingSent();
});

it('lets the parent toggle the preference', function () {
    expect($this->user->refresh()->milestone_emails_enabled)->toBeTrue();

    actingAs($this->user)->putJson('/api/me/preferences', ['milestone_emails_enabled' => false])
        ->assertOk()
        ->assertJson(['milestone_emails_enabled' => false]);

    expect($this->user->refresh()->milestone_emails_enabled)->toBeFalse();

    actingAs($this->user)->getJson('/api/me')
        ->assertJsonPath('user.milestone_emails_enabled', false);
});

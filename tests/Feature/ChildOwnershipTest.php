<?php

use App\Models\BeszedShare;
use App\Models\Child;
use App\Models\User;

use function Pest\Laravel\actingAs;

/*
| Every route that names a child ({child} in the URL) must refuse a parent who is not that child's parent, and a
| visitor who is not signed in. The routes are read from the router, so a new child route is covered the day it is added.
*/
it('lets no other parent, and no visitor, reach a child through any route', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $child = Child::create(['user_id' => $owner->id, 'name' => 'Zoé']);
    actingAs($owner)->postJson("/api/beszed/children/{$child->id}/shares", ['days' => 30, 'label' => 'Kovács Anna'])->assertCreated();
    $share = BeszedShare::sole();

    $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), '{child}'));
    expect($routes->count())->toBeGreaterThan(15); // the test is not silently checking nothing

    $wrongParent = [];
    $notSignedIn = [];
    foreach ($routes as $route) {
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS', 'PATCH']) as $method) {
            $url = '/'.str_replace(['{child}', '{share}'], [$child->id, $share->id], $route->uri());
            expect($url)->not->toContain('{'); // an unfilled parameter would answer 404 before any check

            auth()->forgetGuards();
            $status = actingAs($stranger)->json($method, $url, [])->status();
            if ($status !== 403) {
                $wrongParent[] = "$method $url -> $status";
            }

            auth()->forgetGuards();
            $status = test()->json($method, $url, [])->status();
            if ($status !== 401) {
                $notSignedIn[] = "$method $url -> $status";
            }
        }
    }

    expect($wrongParent)->toBe([])->and($notSignedIn)->toBe([]);

    // The owner is not locked out of their own child.
    auth()->forgetGuards();
    actingAs($owner)->getJson("/api/beszed/children/{$child->id}/progress")->assertOk();
});

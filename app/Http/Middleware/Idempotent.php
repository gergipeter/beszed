<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * A result the app sends again (a retry from its outbox after a lost reply) must count once. The app puts a random
 * `Idempotency-Key` on each result; the first answer to a key is kept for a day and handed back for any repeat,
 * marked `Idempotent-Replayed: true`, without running the request again.
 *
 * The key is scoped to the signed-in parent and the URL, not to the body: the app adds `played_at` to a result when it
 * is queued, so the retry's body is not byte-for-byte the first one. Only successful answers are kept, so a result
 * that failed can be sent again with the same key.
 */
class Idempotent
{
    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();

        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key) || ! $user) {
            return $next($request);
        }

        $id = 'idempotent:'.$user->getAuthIdentifier().':'.sha1($request->method().' '.$request->path()).':'.$key;

        try {
            // Two copies of the same result arriving together: the second waits for the first to finish.
            return Cache::lock("$id:lock", 15)->block(5, function () use ($id, $request, $next) {
                if ($kept = Cache::get($id)) {
                    return response($kept['body'], $kept['status'], $kept['headers'] + ['Idempotent-Replayed' => 'true']);
                }

                $response = $next($request);

                if ($response->isSuccessful()) {
                    Cache::put($id, [
                        'status' => $response->getStatusCode(),
                        'body' => $response->getContent(),
                        'headers' => ['Content-Type' => $response->headers->get('Content-Type', 'application/json')],
                    ], now()->addHours(self::TTL_HOURS));
                }

                return $response;
            });
        } catch (LockTimeoutException) {
            // The first copy is still running: ask the app to try again shortly (it keeps the result and retries).
            return response()->json(['message' => 'Még feldolgozás alatt.'], 503, ['Retry-After' => '2']);
        }
    }
}

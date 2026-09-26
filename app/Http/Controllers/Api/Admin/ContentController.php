<?php

namespace App\Http\Controllers\Api\Admin;

use App\Beszed\Content\ContentRules;
use App\Http\Controllers\Controller;
use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The content editor: words, sentences and picture sets of every game. Items are
 * checked against the game's schema and Hungarian rules (ContentRules) so a typo
 * can't reach a child; edited seed items are left alone by the seeder from then on.
 */
class ContentController extends Controller
{
    /** Games with their form schema and item counts. */
    public function games(): JsonResponse
    {
        $counts = BeszedContentItem::selectRaw('game, SUM(CASE WHEN active THEN 1 ELSE 0 END) as live, COUNT(*) as total')
            ->groupBy('game')->get()->keyBy('game');

        return response()->json(['games' => collect(config('beszed.games'))->map(fn ($g, $id) => [
            'id' => $id,
            'name' => $g['name'],
            'emoji' => $g['emoji'],
            'adaptive' => isset($g['adaptive']),
            'schema' => config("beszed_content.schemas.$id"),
            'active' => (int) ($counts[$id]->live ?? 0),
            'total' => (int) ($counts[$id]->total ?? 0),
        ])->values()]);
    }

    public function index(string $game): JsonResponse
    {
        $this->knownGame($game);
        $used = BeszedAttempt::where('game', $game)->whereNotNull('content_item_id')
            ->groupBy('content_item_id')->selectRaw('content_item_id, COUNT(*) as n')->pluck('n', 'content_item_id');

        $items = BeszedContentItem::where('game', $game)->orderByDesc('active')->orderBy('level')->orderBy('id')->get()
            ->map(fn ($i) => $this->present($i, (int) ($used[$i->id] ?? 0)));

        return response()->json(['items' => $items]);
    }

    public function store(Request $request, string $game): JsonResponse
    {
        $this->knownGame($game);
        $data = $this->validated($request, $game);

        $item = BeszedContentItem::create($data + ['game' => $game, 'source' => 'admin', 'edited_at' => now()]);

        return response()->json(['item' => $this->present($item, 0)], 201);
    }

    public function update(Request $request, string $game, BeszedContentItem $item): JsonResponse
    {
        $this->ownItem($game, $item);
        $data = $this->validated($request, $game, $item->id);

        $item->update($data + ['edited_at' => now()]);

        return response()->json(['item' => $this->present($item, $this->uses($item))]);
    }

    /** Never-played items go; played ones are switched off so the history keeps its reference. */
    public function destroy(string $game, BeszedContentItem $item): JsonResponse
    {
        $this->ownItem($game, $item);

        if ($this->uses($item) === 0 && $item->source === 'admin') {
            $item->delete();

            return response()->json(['deleted' => true]);
        }

        $item->update(['active' => false, 'edited_at' => now()]);

        return response()->json(['deleted' => false, 'item' => $this->present($item, $this->uses($item))]);
    }

    private function validated(Request $request, string $game, ?int $except = null): array
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'min:1', 'max:3'],
            'active' => ['sometimes', 'boolean'],
            'payload' => ['required', 'array'],
        ]);

        // Only the schema's fields, trimmed; unknown keys never reach the games.
        $fields = config("beszed_content.schemas.$game.fields");
        $payload = collect($fields)->keys()
            ->filter(fn ($f) => array_key_exists($f, $data['payload']))
            ->mapWithKeys(fn ($f) => [$f => $this->clean($data['payload'][$f])])
            ->all();

        $errors = ContentRules::check($game, $payload) ?: $this->duplicate($game, $payload, $except);
        if ($errors) {
            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($m, $f) => ["payload.$f" => $m])->all());
        }

        return ['level' => $data['level'], 'payload' => $payload] + (isset($data['active']) ? ['active' => $data['active']] : []);
    }

    /** The same word twice in a game would make two identical cards or options. */
    private function duplicate(string $game, array $payload, ?int $except): array
    {
        $field = config("beszed_content.schemas.$game.title");
        $same = BeszedContentItem::where('game', $game)->where('active', true)
            ->when($except, fn ($q) => $q->whereKeyNot($except))->get(['id', 'payload'])
            ->contains(fn ($i) => mb_strtolower(json_encode($i->payload[$field] ?? null)) === mb_strtolower(json_encode($payload[$field] ?? null)));

        return $same && isset($payload[$field]) ? [$field => 'Ez már szerepel ebben a játékban.'] : [];
    }

    private function clean(mixed $value): mixed
    {
        return match (true) {
            is_string($value) => trim($value),
            is_array($value) => array_map(fn ($v) => $this->clean($v), $value),
            default => $value,
        };
    }

    private function uses(BeszedContentItem $item): int
    {
        return BeszedAttempt::where('content_item_id', $item->id)->count();
    }

    private function present(BeszedContentItem $i, int $uses): array
    {
        return $i->only('id', 'level', 'payload', 'active', 'source') + [
            'edited' => (bool) $i->edited_at,
            'uses' => $uses,
        ];
    }

    private function knownGame(string $game): void
    {
        abort_unless(config("beszed_content.schemas.$game") && config("beszed.games.$game"), 404);
    }

    private function ownItem(string $game, BeszedContentItem $item): void
    {
        $this->knownGame($game);
        abort_unless($item->game === $game, 404);
    }
}

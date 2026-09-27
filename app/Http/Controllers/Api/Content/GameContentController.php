<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Controller;
use App\Models\BeszedContentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Public API for populating game content via API.
 * - GET /api/content/{game}: list items
 * - POST /api/content/{game}: create item
 * - POST /api/content/{game}/bulk: bulk import items
 * - PUT /api/content/{game}/{id}: update item
 * - DELETE /api/content/{game}/{id}: delete item
 */
class GameContentController extends Controller
{
    public function index(string $game): JsonResponse
    {
        $this->validateGame($game);

        $query = BeszedContentItem::where('game', $game)
            ->where('active', true)
            ->where('status', 'live')
            ->orderBy('level')
            ->orderBy('id');

        $total = $query->count();
        $items = $query->limit(100)->get();

        return response()->json([
            'game' => $game,
            'total' => $total,
            'count' => $items->count(),
            'items' => $items->map(function ($item) { return $this->format($item); }),
        ]);
    }

    public function store(Request $request, string $game): JsonResponse
    {
        $this->validateGame($game);
        $data = $this->validateItem($request, $game);

        $item = BeszedContentItem::create($data + [
            'game' => $game,
            'source' => 'api',
            'active' => true,
            'status' => 'live',
        ]);

        return response()->json(['item' => $this->format($item)], 201);
    }

    public function update(Request $request, string $game, int $id): JsonResponse
    {
        $this->validateGame($game);
        $item = $this->findItem($game, $id);

        $data = $this->validateItem($request, $game, $id);
        $item->update($data + ['edited_at' => now()]);

        return response()->json(['item' => $this->format($item)]);
    }

    public function destroy(string $game, int $id): JsonResponse
    {
        $this->validateGame($game);
        $item = $this->findItem($game, $id);

        if ($item->source === 'seed') {
            return response()->json(['error' => 'Cannot delete seeded content. Deactivate instead.'], 422);
        }

        $item->delete();

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    public function bulk(Request $request, string $game): JsonResponse
    {
        $this->validateGame($game);
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['required', 'array'],
        ]);

        $results = ['imported' => 0, 'errors' => []];

        foreach ($data['items'] as $index => $itemData) {
            try {
                $validated = $this->validateItemData($itemData, $game);
                $item = BeszedContentItem::create($validated + [
                    'game' => $game,
                    'source' => 'api',
                    'active' => $itemData['active'] ?? true,
                    'status' => $itemData['status'] ?? 'live',
                ]);
                $results['imported']++;
            } catch (ValidationException $e) {
                $results['errors'][] = [
                    'index' => $index,
                    'errors' => $e->errors(),
                ];
            }
        }

        return response()->json($results);
    }

    private function validateGame(string $game): void
    {
        abort_unless(config("beszed_content.schemas.$game") && config("beszed.games.$game"), 404);
    }

    private function findItem(string $game, int $id)
    {
        $item = BeszedContentItem::find($id);
        abort_unless($item && $item->game === $game, 404);

        return $item;
    }

    private function validateItem(Request $request, string $game, ?int $except = null): array
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'min:1', 'max:3'],
            'payload' => ['required', 'array'],
        ]);

        return $this->validateItemData($data, $game, $except);
    }

    private function validateItemData(array $data, string $game, ?int $except = null): array
    {
        $schema = config("beszed_content.schemas.$game");
        $payload = $data['payload'] ?? [];

        $errors = [];
        foreach ($schema['fields'] as $field => $spec) {
            if (!isset($payload[$field]) && ($spec['required'] ?? false)) {
                $errors[$field] = 'This field is required.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($m, $f) => ["payload.$f" => $m])->all());
        }

        return [
            'level' => $data['level'] ?? 1,
            'payload' => array_intersect_key($payload, array_flip(array_keys($schema['fields']))),
        ];
    }

    private function format(BeszedContentItem $item): array
    {
        return [
            'id' => $item->id,
            'game' => $item->game,
            'level' => $item->level,
            'payload' => $item->payload,
            'active' => $item->active,
            'status' => $item->status,
            'source' => $item->source,
        ];
    }
}

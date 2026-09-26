<?php

namespace App\Http\Controllers\Api\Admin;

use App\Beszed\Content\ContentRules;
use App\Http\Controllers\Controller;
use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use App\Models\BeszedContentItemEdit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $this->audit($item, 'created', null, $item->only('level', 'payload', 'active', 'status'), $request);

        return response()->json(['item' => $this->present($item, 0)], 201);
    }

    public function update(Request $request, string $game, BeszedContentItem $item): JsonResponse
    {
        $this->ownItem($game, $item);
        $before = $item->only('level', 'payload', 'active', 'status');
        $data = $this->validated($request, $game, $item->id);

        $item->update($data + ['edited_at' => now()]);

        $this->audit($item, 'updated', $before, $item->only('level', 'payload', 'active', 'status'), $request);

        return response()->json(['item' => $this->present($item, $this->uses($item))]);
    }

    /** Never-played items go; played ones are switched off so the history keeps its reference. */
    public function destroy(string $game, BeszedContentItem $item): JsonResponse
    {
        $this->ownItem($game, $item);
        $result = $this->deactivateOrDelete($item, request());

        if ($result['deleted']) {
            return response()->json($result);
        }

        $item->refresh();

        return response()->json(['deleted' => false, 'item' => $this->present($item, $this->uses($item))]);
    }

    private function validated(Request $request, string $game, ?int $except = null): array
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'min:1', 'max:3'],
            'active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['draft', 'live'])],
            'payload' => ['required', 'array'],
        ]);

        $payload = $this->buildPayload($game, $data['payload']);
        $errors = $this->validateAndDuplicate($game, $payload, $except);
        if ($errors) {
            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($m, $f) => ["payload.$f" => $m])->all());
        }

        $result = ['level' => $data['level'], 'payload' => $payload];
        if (isset($data['active'])) {
            $result['active'] = $data['active'];
        }
        if (isset($data['status'])) {
            $result['status'] = $data['status'];
        }

        return $result;
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
        return $i->only('id', 'level', 'payload', 'active', 'source', 'status') + [
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

    private function audit(BeszedContentItem $item, string $action, ?array $before, ?array $after, Request $request): void
    {
        BeszedContentItemEdit::create([
            'content_item_id' => $item->id,
            'editor_email' => strtolower($request->user()->email),
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }

    /** Build and validate payload from raw input; returns cleaned array or throws ValidationException. */
    private function buildPayload(string $game, array $payloadInput): array
    {
        $fields = config("beszed_content.schemas.$game.fields");
        return collect($fields)->keys()
            ->filter(fn ($f) => array_key_exists($f, $payloadInput))
            ->mapWithKeys(fn ($f) => [$f => $this->clean($payloadInput[$f])])
            ->all();
    }

    /** Validate payload against rules and duplicates; returns error map or null if valid. */
    private function validateAndDuplicate(string $game, array $payload, ?int $except = null): ?array
    {
        $errors = ContentRules::check($game, $payload);
        if ($errors) {
            return $errors;
        }

        return $this->duplicate($game, $payload, $except) ?: null;
    }

    /** Hard-delete if unused+admin-sourced, else soft-deactivate; performs audit and returns response array. */
    private function deactivateOrDelete(BeszedContentItem $item, Request $request): array
    {
        $before = $item->only('level', 'payload', 'active', 'status');

        if ($this->uses($item) === 0 && $item->source === 'admin') {
            $this->audit($item, 'deleted', $before, null, $request);
            $item->delete();

            return ['id' => $item->id, 'deleted' => true];
        }

        $item->update(['active' => false, 'edited_at' => now()]);
        $this->audit($item, 'deactivated', $before, $item->only('level', 'payload', 'active', 'status'), $request);

        return ['id' => $item->id, 'deleted' => false];
    }

    /** Export items as CSV for a game. */
    public function export(string $game): \Illuminate\Http\Response
    {
        $this->knownGame($game);

        $items = BeszedContentItem::where('game', $game)->orderBy('id')->get();
        $fields = config("beszed_content.schemas.$game.fields");

        return response()->streamDownload(function () use ($items, $fields) {
            $out = fopen('php://output', 'w');

            $header = ['id', 'level', 'active', 'status', 'source', ...array_keys($fields)];
            fputcsv($out, $header);

            foreach ($items as $item) {
                $row = [$item->id, $item->level, (int) $item->active, $item->status, $item->source];

                foreach ($fields as $key => $spec) {
                    $value = $item->payload[$key] ?? null;
                    $row[] = match ($spec['type']) {
                        'list', 'emoji_list' => implode(';', $value ?? []),
                        'pairs' => implode(';', array_map(fn ($p) => "{$p[0]}:{$p[1]}", $value ?? [])),
                        default => $value,
                    };
                }

                fputcsv($out, $row);
            }

            fclose($out);
        }, "{$game}.csv");
    }

    /** Import items from CSV for a game; returns per-row result summary. */
    public function import(Request $request, string $game): JsonResponse
    {
        $this->knownGame($game);
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $results = ['imported' => 0, 'errors' => []];
        $fields = config("beszed_content.schemas.$game.fields");

        $handle = fopen($data['file']->getRealPath(), 'r');
        $header = fgetcsv($handle);

        $rowNum = 2;
        while (($row = fgetcsv($handle)) !== false) {
            $mapped = array_combine($header, $row);
            $payload = [];

            foreach ($fields as $key => $spec) {
                if (!isset($mapped[$key])) {
                    continue;
                }

                $val = $mapped[$key];
                $payload[$key] = match ($spec['type']) {
                    'list', 'emoji_list' => $val ? explode(';', $val) : [],
                    'pairs' => $val ? array_map(fn ($p) => explode(':', $p, 2), explode(';', $val)) : [],
                    default => $val,
                };
            }

            $errs = $this->validateAndDuplicate($game, $payload);
            if ($errs) {
                $results['errors'][] = ['row' => $rowNum, 'errors' => $errs];
                $rowNum++;
                continue;
            }

            $item = BeszedContentItem::create([
                'game' => $game,
                'level' => (int) ($mapped['level'] ?? 1),
                'payload' => $payload,
                'active' => (bool) ($mapped['active'] ?? true),
                'source' => 'admin',
                'edited_at' => now(),
                'status' => 'draft',
            ]);

            $this->audit($item, 'created', null, $item->only('level', 'payload', 'active', 'status'), $request);
            $results['imported']++;
            $rowNum++;
        }

        fclose($handle);

        return response()->json($results);
    }

    /** Bulk activate/deactivate/delete items. */
    public function bulk(Request $request, string $game): JsonResponse
    {
        $this->knownGame($game);
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
        ]);

        $items = BeszedContentItem::where('game', $game)->whereIn('id', $data['ids'])->get();
        $results = [];

        foreach ($items as $item) {
            match ($data['action']) {
                'activate' => (
                    $item->update(['active' => true, 'edited_at' => now()]),
                    $this->audit($item, 'updated', ['active' => false], ['active' => true], $request)
                ),
                'deactivate' => (
                    $item->update(['active' => false, 'edited_at' => now()]),
                    $this->audit($item, 'updated', ['active' => true], ['active' => false], $request)
                ),
                'delete' => $results[] = $this->deactivateOrDelete($item, $request),
            };
        }

        return response()->json(['results' => $results]);
    }
}
